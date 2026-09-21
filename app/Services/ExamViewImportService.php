<?php

namespace App\Services;

use DOMDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
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
            // Check if user accidentally zipped a raw .bnk file instead of exporting
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                if (str_ends_with(strtolower($entry), '.bnk')) {
                    throw new \RuntimeException('Berkas ZIP memuat berkas .bnk mentah ('.basename($entry).'). Alsenform membutuhkan file ZIP hasil ekspor ExamView. Silakan buka bank soal di aplikasi ExamView Test Generator, klik menu File -> Export -> Blackboard 7.1-9.0 (atau Blackboard 6.0-7.0), lalu unggah file ZIP hasil ekspor tersebut.');
                }
            }

            $xmlContents = $this->findAssessmentXmlFiles($zip);

            if (empty($xmlContents)) {
                throw new \RuntimeException('Berkas ZIP tidak memuat data bank soal ExamView / Blackboard yang valid (tidak ditemukan berkas XML/DAT QTI). Pastikan mengekspor dengan format Blackboard 7.1-9.0 atau Blackboard 6.0-7.0 dari ExamView Test Generator.');
            }

            $questions = [];
            $nextId = 1000;

            foreach ($xmlContents as $xmlString) {
                $parsed = $this->parseQtiXml($xmlString, $zip, $nextId);
                $questions = array_merge($questions, $parsed);
            }

            if (empty($questions)) {
                throw new \RuntimeException('Tidak ditemukan pertanyaan yang dapat diurai di dalam berkas bank soal. Pastikan berkas ZIP berisi butir soal pilihan ganda, benar/salah, atau esai.');
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
        $preferredHrefs = [];

        // Check imsmanifest.xml to find referenced resource files
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            if (strcasecmp(basename($entryName), 'imsmanifest.xml') === 0) {
                $manifest = $zip->getFromIndex($i);
                if ($manifest && preg_match_all('/<resource[^>]+href=["\']([^"\']+)["\']/i', $manifest, $m)) {
                    foreach ($m[1] as $href) {
                        $preferredHrefs[] = strtolower(basename($href));
                    }
                }
                break;
            }
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);

            // Skip directory entries and manifest
            if (str_ends_with($entryName, '/') || strcasecmp(basename($entryName), 'imsmanifest.xml') === 0) {
                continue;
            }

            // Skip binary media files
            $ext = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'bmp', 'svg', 'webp', 'mp3', 'wav', 'mp4', 'avi', 'pdf', 'ico', 'woff', 'ttf'], true)) {
                continue;
            }

            $raw = $zip->getFromIndex($i);
            if (! $raw) {
                continue;
            }

            $baseNameLower = strtolower(basename($entryName));

            // Check if it is a recognized assessment resource from manifest or has assessment markers
            if (
                in_array($baseNameLower, $preferredHrefs, true) ||
                stripos($raw, '<questestinterop') !== false ||
                stripos($raw, '<assessment') !== false ||
                stripos($raw, '<pool') !== false ||
                stripos($raw, '<item') !== false ||
                stripos($raw, '<question') !== false
            ) {
                $contents[] = $raw;
            }
        }

        return $contents;
    }

    /**
     * Clean and sanitize raw XML string to avoid libxml parse errors and namespace blocking.
     */
    private function sanitizeXmlString(string $xml): string
    {
        // 1. Detect and handle UTF-16 BOM or encodings
        if (str_starts_with($xml, "\xFF\xFE") || str_starts_with($xml, "\xFE\xFF")) {
            $xml = mb_convert_encoding($xml, 'UTF-8', 'UTF-16');
        } elseif (preg_match('/<\?xml[^>]+encoding=["\'](UTF-16[A-Z]*)["\']/i', substr($xml, 0, 200), $m)) {
            $xml = mb_convert_encoding($xml, 'UTF-8', $m[1]);
        }

        // 2. Remove UTF-8 BOM if present
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml);

        // 3. Remove non-printable control characters that break XML (preserve tab, newline, carriage return)
        $xml = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $xml);

        // 4. If declared encoding is ISO-8859-1 or Windows-1252, convert to UTF-8
        if (preg_match('/<\?xml[^>]+encoding=["\']([^"\']+)["\']/i', substr($xml, 0, 500), $m)) {
            $declaredEncoding = strtoupper(trim($m[1]));
            if ($declaredEncoding !== 'UTF-8') {
                $converted = @mb_convert_encoding($xml, 'UTF-8', $declaredEncoding);
                if ($converted !== false && $converted !== '') {
                    $xml = preg_replace('/(<\?xml[^>]+encoding=["\'])[^"\']+(["\'])/i', '$1UTF-8$2', $converted);
                }
            }
        }

        // 5. Convert all named HTML entities except standard XML entities (amp, lt, gt, quot, apos)
        $xml = preg_replace_callback('/&([a-zA-Z0-9]+);/', function ($matches) {
            $entity = $matches[1];
            if (in_array(strtolower($entity), ['amp', 'lt', 'gt', 'quot', 'apos'], true)) {
                return '&'.$entity.';';
            }

            $decoded = html_entity_decode('&'.$entity.';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === '&'.$entity.';') {
                return ' ';
            }

            return match ($decoded) {
                '<' => '&lt;',
                '>' => '&gt;',
                '&' => '&amp;',
                '"' => '&quot;',
                "'" => '&apos;',
                default => $decoded,
            };
        }, $xml);

        // 6. Fix unescaped ampersands: replace & that is NOT followed by a valid XML entity
        $xml = preg_replace('/&(?!(?:amp|lt|gt|quot|apos|#\d+|#x[a-f\d]+);)/i', '&amp;', $xml);

        // 7. Strip xmlns="..." and xmlns:prefix="..." attributes so xpath works unconditionally
        $xml = preg_replace('/\sxmlns(:\w+)?=(["\']).*?\2/s', '', $xml);

        // 8. Strip namespace prefixes from tags e.g. <bb:item> -> <item>, </bb:item> -> </item>
        $xml = preg_replace_callback('/<(\/?)[a-zA-Z0-9_\-]+:([a-zA-Z0-9_\-]+)/', fn ($m) => '<'.$m[1].$m[2], $xml);

        return $xml;
    }

    /**
     * Parse QTI XML into Alsenform Question structures.
     *
     * @return list<array<string, mixed>>
     */
    private function parseQtiXml(string $rawXmlString, ZipArchive $zip, int &$nextId): array
    {
        $sanitizedXml = $this->sanitizeXmlString($rawXmlString);

        libxml_use_internal_errors(true);

        $libxmlOptions = LIBXML_NOCDATA | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET;
        if (defined('LIBXML_PARSEHUGE')) {
            $libxmlOptions |= LIBXML_PARSEHUGE;
        }

        $root = simplexml_load_string(
            $sanitizedXml,
            SimpleXMLElement::class,
            $libxmlOptions
        );

        if ($root === false) {
            $dom = new DOMDocument;
            @$dom->loadXML($sanitizedXml, $libxmlOptions);
            $root = @simplexml_import_dom($dom);
        }

        if ($root === false) {
            Log::warning('ExamView import SimpleXMLElement parse failure.');

            return [];
        }

        // Search for questions in either QTI 1.2 format (<item>) or Blackboard 7.1+ format (<QUESTION>)
        $items = $root->xpath('//item | //ITEM | //QUESTION | //question | //ITEM_RECORD | //item_record') ?: [];

        if (empty($items)) {
            $rootName = strtolower($root->getName());
            if (in_array($rootName, ['item', 'question', 'item_record'], true)) {
                $items = [$root];
            }
        }

        if (empty($items)) {
            return [];
        }

        $questions = [];

        foreach ($items as $item) {
            $nodeName = strtolower($item->getName());

            if ($nodeName === 'question') {
                $parsed = $this->parseBlackboard7Question($item, $zip, $nextId);
            } else {
                $parsed = $this->parseQti12Item($item, $zip, $nextId);
            }

            if ($parsed !== null) {
                $questions[] = $parsed;
            }
        }

        return $questions;
    }

    /**
     * Parse standard IMS QTI 1.2 format (<item>) from Blackboard 6.0 - 7.0.
     *
     * @return array<string, mixed>|null
     */
    private function parseQti12Item(SimpleXMLElement $item, ZipArchive $zip, int &$nextId): ?array
    {
        // 1. Determine question type
        $bbType = (string) ($item->xpath('.//itemmetadata/bbmd_questiontype | .//itemmetadata/qmd_questiontype')[0] ?? '');
        $type = $this->mapQuestionType($bbType);

        // Special handling for Matching questions in Blackboard 6.0 - 7.0
        if ($type === 'Multiple-choice grid') {
            return $this->parseQti12MatchingItem($item, $zip, $nextId);
        }

        // 2. Extract question prompt and embedded media
        $extractedPrompt = $this->extractQti12PromptAndMedia($item, $zip);
        $title = $extractedPrompt['title'];
        $media = $extractedPrompt['media'];

        // 3. Extract options
        $optionsData = $this->extractQti12Options($item);
        $options = $optionsData['options'];
        $identMap = $optionsData['identMap']; // ident => index

        // True/False fallback options
        if ($type === 'Multiple choice' && strcasecmp($bbType, 'True/False') === 0 && empty($options)) {
            $options = ['Benar', 'Salah'];
            $identMap['true'] = 0;
            $identMap['false'] = 1;
            $identMap['benar'] = 0;
            $identMap['salah'] = 1;
        }

        // If title is blank but options/media exist, do not discard question
        if (trim($title) === '' && empty($media) && empty($options)) {
            return null;
        }

        if (trim($title) === '') {
            $title = 'Pertanyaan '.$nextId;
        }

        // 4. Extract points and answer key
        $pointsAndAnswer = $this->extractQti12PointsAndAnswer($item, $type, $options, $identMap, $bbType);

        return [
            'id' => $nextId++,
            'title' => $title,
            'description' => '',
            'type' => $type,
            'options' => $options,
            'rows' => [],
            'columns' => [],
            'answer' => $pointsAndAnswer['answer'],
            'required' => false,
            'media' => $media,
            'points' => $pointsAndAnswer['points'],
        ];
    }

    /**
     * Parse Blackboard 7.1+ format (<QUESTION>).
     *
     * @return array<string, mixed>|null
     */
    private function parseBlackboard7Question(SimpleXMLElement $item, ZipArchive $zip, int &$nextId): ?array
    {
        // Type detection
        $typeAttr = (string) ($item['type'] ?? $item->xpath('.//DATED/@type | .//dated/@type')[0] ?? '');
        $type = $this->mapQuestionType($typeAttr);

        // Title / Prompt: Look for text inside BODY/TEXT, PROMPT, etc.
        $candidatePromptNodes = $item->xpath('.//BODY/TEXT | .//body/text | .//PROMPT/TEXT | .//prompt/text | .//PROMPT | .//prompt | .//BODY//text()');
        $rawPrompt = '';
        foreach ($candidatePromptNodes as $cp) {
            $txt = trim((string) $cp);
            if ($txt !== '') {
                $rawPrompt = $txt;
                break;
            }
        }
        if ($rawPrompt === '') {
            $rawPrompt = (string) ($item['title'] ?? '');
        }
        $extracted = $this->cleanHtmlAndExtractImages($rawPrompt, $zip);

        $title = $extracted['text'];
        $media = $extracted['media'];

        // Options
        $options = [];
        $identMap = [];
        $answerNodes = $item->xpath('.//ANSWER | .//answer | .//CHOICE | .//choice | .//RESPONSE | .//response');

        foreach ($answerNodes as $ans) {
            $ansId = (string) ($ans['id'] ?? $ans['ident'] ?? $ans['answer_id'] ?? '');
            $ansTextNode = $ans->xpath('.//TEXT | .//text | .//material/mattext | .//mattext')[0] ?? null;
            $optRaw = $ansTextNode ? (string) $ansTextNode : (string) $ans;
            $cleanOpt = trim(strip_tags(html_entity_decode($optRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

            if ($cleanOpt === '') {
                $cleanOpt = 'Pilihan '.(count($options) + 1);
            }

            $options[] = $cleanOpt;
            if ($ansId !== '') {
                $identMap[$ansId] = count($options) - 1;
            }
        }

        // True/False fallback options
        if ($type === 'Multiple choice' && (strcasecmp($typeAttr, 'TF') === 0 || strcasecmp($typeAttr, 'True/False') === 0) && empty($options)) {
            $options = ['Benar', 'Salah'];
            $identMap['true'] = 0;
            $identMap['false'] = 1;
            $identMap['1'] = 0;
            $identMap['0'] = 1;
        }

        // Answer Key
        $correctAnswerNodes = $item->xpath('.//GRADABLE//CORRECTANSWER | .//gradable//correctanswer | .//CORRECTANSWER | .//correctanswer | .//CORRECT_ANSWER | .//correct_answer');

        if ($type === 'Checkboxes') {
            $correctAnswers = [];
            foreach ($correctAnswerNodes as $can) {
                $cId = (string) ($can['answer_id'] ?? $can['id'] ?? $can['ident'] ?? trim((string) $can));
                if (isset($identMap[$cId]) && isset($options[$identMap[$cId]])) {
                    $correctAnswers[] = $options[$identMap[$cId]];
                } elseif (preg_match('/^[A-E]$/i', $cId)) {
                    $idx = ord(strtoupper($cId)) - ord('A');
                    if (isset($options[$idx])) {
                        $correctAnswers[] = $options[$idx];
                    }
                }
            }
            $answer = array_values(array_unique($correctAnswers));
        } elseif ($type === 'Short answer' || $type === 'Paragraph') {
            $ansText = '';
            if (! empty($correctAnswerNodes)) {
                $ansText = trim((string) $correctAnswerNodes[0]);
            }
            $answer = $ansText;
        } else {
            $correctAnswerNode = $correctAnswerNodes[0] ?? null;
            $correctAnswerId = (string) ($correctAnswerNode['answer_id'] ?? $correctAnswerNode['id'] ?? $correctAnswerNode['ident'] ?? '');

            if ($correctAnswerId === '' && $correctAnswerNode) {
                $correctAnswerId = trim((string) $correctAnswerNode);
            }

            $answer = 0;
            if (isset($identMap[$correctAnswerId])) {
                $answer = $identMap[$correctAnswerId];
            } elseif (preg_match('/^[A-E]$/i', $correctAnswerId)) {
                $idx = ord(strtoupper($correctAnswerId)) - ord('A');
                if (isset($options[$idx])) {
                    $answer = $idx;
                }
            }
        }

        // Points
        $points = 10;
        $pointsNode = $item->xpath('.//GRADABLE//POINTS_POSSIBLE | .//gradable//points_possible | .//POINTS_POSSIBLE | .//points_possible')[0] ?? null;
        if ($pointsNode && (float) $pointsNode > 0) {
            $points = (int) round((float) $pointsNode);
        }

        if (trim($title) === '' && empty($media) && empty($options)) {
            return null;
        }

        return [
            'id' => $nextId++,
            'title' => $title ?: 'Pertanyaan '.$nextId,
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

    /**
     * Map Blackboard/ExamView question type string to Alsenform question type.
     */
    private function mapQuestionType(string $bbType): string
    {
        $normalized = strtolower(str_replace([' ', '/', '-', '_'], '', trim($bbType)));

        return match ($normalized) {
            'multiplechoice', 'mc' => 'Multiple choice',
            'truefalse', 'tf', 'yesno' => 'Multiple choice',
            'multipleanswer', 'multipleresponse', 'ma' => 'Checkboxes',
            'essay', 'paragraph', 'ess' => 'Paragraph',
            'shortanswer', 'shortresponse', 'numeric', 'numericresponse', 'fillintheblank', 'fib', 'num', 'sr' => 'Short answer',
            'matching', 'mat' => 'Multiple-choice grid',
            default => 'Multiple choice',
        };
    }

    /**
     * Extract prompt text and any embedded image references for QTI 1.2.
     *
     * @return array{title: string, media: list<array{type: 'image', url: string}>}
     */
    private function extractQti12PromptAndMedia(SimpleXMLElement $item, ZipArchive $zip): array
    {
        // 1. Check for mat_formattedtext or mattext specifically inside QUESTION_BLOCK
        $promptNodes = $item->xpath('.//presentation//flow[@class="QUESTION_BLOCK"]//mat_formattedtext | .//presentation//flow[@class="QUESTION_BLOCK"]//mattext');

        if (empty($promptNodes)) {
            $promptNodes = $item->xpath('.//presentation//material/mattext[not(ancestor::response_label) and not(ancestor::response_lid)] | .//presentation//flow_mat/material/mattext[not(ancestor::response_label) and not(ancestor::response_lid)] | .//presentation/flow/material/mattext[not(ancestor::response_label) and not(ancestor::response_lid)]');
        }

        if (empty($promptNodes)) {
            $promptNodes = $item->xpath('.//presentation//mat_formattedtext[not(ancestor::response_label) and not(ancestor::response_lid) and not(ancestor::flow[@class="RESPONSE_BLOCK"]) and not(ancestor::flow[@class="RIGHT_MATCH_BLOCK"])] | .//presentation//mattext[not(ancestor::response_label) and not(ancestor::response_lid)]');
        }

        if (empty($promptNodes)) {
            $promptNodes = $item->xpath('.//mat_formattedtext[not(ancestor::response_label) and not(ancestor::render_choice) and not(ancestor::response_lid) and not(ancestor::flow[@class="RESPONSE_BLOCK"]) and not(ancestor::flow[@class="RIGHT_MATCH_BLOCK"])] | .//mattext[not(ancestor::response_label) and not(ancestor::render_choice) and not(ancestor::response_lid)]');
        }

        if (empty($promptNodes)) {
            $promptNodes = $item->xpath('.//presentation//text | .//BODY//TEXT | .//body//text | .//PROMPT | .//prompt');
        }

        $rawHtml = '';
        if ($promptNodes) {
            foreach ($promptNodes as $node) {
                $rawHtml .= "\n".(string) $node;
            }
        }

        $extracted = $this->cleanHtmlAndExtractImages($rawHtml, $zip);

        // Fallback: If text is empty, check item title attribute
        if (trim($extracted['text']) === '') {
            $fallbackTitle = trim((string) ($item['title'] ?? $item['ident'] ?? ''));
            if ($fallbackTitle !== '') {
                $extracted['text'] = $fallbackTitle;
            }
        }

        // Check for standalone <matimage> tags in presentation
        $imageNodes = $item->xpath('.//presentation//matimage | .//material//matimage');
        if ($imageNodes) {
            foreach ($imageNodes as $imgNode) {
                $uri = urldecode(trim((string) ($imgNode['uri'] ?? $imgNode['URI'] ?? '')));
                if ($uri !== '') {
                    $extractedUrl = $this->extractAndStoreImage($uri, $zip);
                    if ($extractedUrl) {
                        $extracted['media'][] = [
                            'type' => 'image',
                            'url' => $extractedUrl,
                        ];
                    }
                }
            }
        }

        return [
            'title' => $extracted['text'],
            'media' => $extracted['media'],
        ];
    }

    /**
     * Clean HTML text and extract any embedded images.
     *
     * @return array{text: string, media: list<array{type: 'image', url: string}>}
     */
    private function cleanHtmlAndExtractImages(string $rawHtml, ZipArchive $zip): array
    {
        $media = [];

        // Check for <img> tags inside raw HTML (e.g. ExamView embedded file location)
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $rawHtml, $matches)) {
            foreach ($matches[1] as $src) {
                // Clean @X@EmbeddedFile.location@X@ pattern
                $cleanPath = urldecode(preg_replace('/^@X@[^@]+@X@/i', '', $src));
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
        $cleanText = preg_replace('/@X@[^@]+@X@\S*/i', '', $cleanText);
        $cleanText = strip_tags($cleanText);
        $cleanText = trim(preg_replace('/\s+/', ' ', $cleanText));

        return [
            'text' => $cleanText,
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
     * Extract options from response_label elements for QTI 1.2.
     *
     * @return array{options: list<string>, identMap: array<string, int>}
     */
    private function extractQti12Options(SimpleXMLElement $item): array
    {
        $options = [];
        $identMap = [];

        $labels = $item->xpath('.//render_choice//response_label | .//response_lid//response_label | .//response_label | .//RESPONSE_LABEL');
        if (! $labels) {
            return [
                'options' => $options,
                'identMap' => $identMap,
            ];
        }

        foreach ($labels as $label) {
            $ident = (string) ($label['ident'] ?? $label['IDENT'] ?? $label['id'] ?? '');
            $textNode = $label->xpath('.//mat_formattedtext | .//material/mattext | .//mattext | .//text | .//TEXT')[0] ?? null;
            $optText = $textNode ? (string) $textNode : (string) $label;

            $cleanOpt = html_entity_decode($optText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $cleanOpt = trim(strip_tags($cleanOpt));

            if ($cleanOpt === '') {
                $imgNode = $label->xpath('.//matimage | .//img')[0] ?? null;
                if ($imgNode) {
                    $cleanOpt = '[Gambar Pilihan '.(count($options) + 1).']';
                } else {
                    $cleanOpt = 'Pilihan '.(count($options) + 1);
                }
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
     * Extract score weighting and answer key for QTI 1.2.
     *
     * @param  list<string>  $options
     * @param  array<string, int>  $identMap
     * @return array{points: int, answer: mixed}
     */
    private function extractQti12PointsAndAnswer(SimpleXMLElement $item, string $type, array $options, array $identMap, string $bbType): array
    {
        $points = 10;
        $answer = 0;

        // Try extracting points
        $scoreNode = $item->xpath('.//resprocessing//respcondition[@title="correct"]//setvar[@varname="SCORE"] | .//resprocessing//setvar[@varname="SCORE" and number(.) > 0]')[0] ?? null;
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

        // Find respcondition with SCORE > 0 or title="correct"
        $correctConditions = $item->xpath('.//resprocessing//respcondition[contains(@title, "correct") or contains(@title, "Correct") or .//setvar[number(text()) > 0]]');
        if (empty($correctConditions)) {
            $correctConditions = $item->xpath('.//resprocessing//respcondition[.//setvar]');
        }

        $targetCondition = $correctConditions[0] ?? null;

        // Extract answer for Multiple choice
        if ($type === 'Multiple choice') {
            $varequalNodes = $targetCondition
                ? $targetCondition->xpath('.//conditionvar//varequal[not(ancestor::not)] | .//varequal[not(ancestor::not)]')
                : $item->xpath('.//resprocessing//conditionvar//varequal[not(ancestor::not)] | .//conditionvar//varequal[not(ancestor::not)]');

            if ($varequalNodes) {
                $correctIdent = trim((string) $varequalNodes[0]);
                if (isset($identMap[$correctIdent])) {
                    $answer = $identMap[$correctIdent];
                } elseif (strcasecmp($bbType, 'True/False') === 0 || strcasecmp($correctIdent, 'true') === 0 || strcasecmp($correctIdent, 'false') === 0) {
                    if (strcasecmp($correctIdent, 'true') === 0 || $correctIdent === '1' || strcasecmp($correctIdent, 'benar') === 0) {
                        $answer = 0; // Benar
                    } else {
                        $answer = 1; // Salah
                    }
                } elseif (preg_match('/^[A-E]$/i', $correctIdent)) {
                    $idx = ord(strtoupper($correctIdent)) - ord('A');
                    if (isset($options[$idx])) {
                        $answer = $idx;
                    }
                } elseif (preg_match('/_(\d+)$/', $correctIdent, $m)) {
                    $idx = (int) $m[1];
                    if (isset($options[$idx])) {
                        $answer = $idx;
                    } elseif ($idx > 0 && isset($options[$idx - 1])) {
                        $answer = $idx - 1;
                    }
                }
            }
        } elseif ($type === 'Checkboxes') {
            // Multiple answers: ignore negated conditions
            $varequalNodes = $targetCondition
                ? $targetCondition->xpath('.//conditionvar//varequal[not(ancestor::not)] | .//varequal[not(ancestor::not)]')
                : $item->xpath('.//resprocessing//conditionvar//varequal[not(ancestor::not)] | .//conditionvar//varequal[not(ancestor::not)]');

            // Detect if resprocessing uses 0-based index idents (e.g. answer_0) while labels were 1-based
            $isZeroBased = false;
            foreach ($varequalNodes as $ve) {
                $rawId = trim((string) $ve);
                if (preg_match('/^(?:answer_)?0$/i', $rawId) && ! isset($identMap[$rawId])) {
                    $isZeroBased = true;
                    break;
                }
            }

            $correctAnswers = [];
            foreach ($varequalNodes as $ve) {
                $id = trim((string) $ve);

                if ($isZeroBased && preg_match('/_(\d+)$/', $id, $m)) {
                    $idx = (int) $m[1];
                    if (isset($options[$idx])) {
                        $correctAnswers[] = $options[$idx];
                    }
                } elseif (isset($identMap[$id]) && isset($options[$identMap[$id]])) {
                    $correctAnswers[] = $options[$identMap[$id]];
                } elseif (preg_match('/_(\d+)$/', $id, $m)) {
                    $idx = (int) $m[1];
                    if (isset($options[$idx])) {
                        $correctAnswers[] = $options[$idx];
                    } elseif ($idx > 0 && isset($options[$idx - 1])) {
                        $correctAnswers[] = $options[$idx - 1];
                    }
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

    /**
     * Parse IMS QTI 1.2 Matching question into Multiple-choice grid.
     *
     * @return array<string, mixed>
     */
    private function parseQti12MatchingItem(SimpleXMLElement $item, ZipArchive $zip, int &$nextId): array
    {
        // 1. Question Prompt
        $promptNodes = $item->xpath('.//flow[@class="QUESTION_BLOCK"]//mat_formattedtext | .//flow[@class="QUESTION_BLOCK"]//mattext');
        $extracted = $this->cleanHtmlAndExtractImages((string) ($promptNodes[0] ?? ''), $zip);
        $title = $extracted['text'];
        $media = $extracted['media'];

        if ($title === '') {
            $title = 'Pasangkan pernyataan berikut dengan pilihan yang benar';
        }

        // 2. Rows (Left statements) and row response identifier mapping
        $rows = [];
        $rowRespMap = []; // rowIndex => response_lid ident (e.g. answer_1)
        $leftBlocks = $item->xpath('.//flow[@class="RESPONSE_BLOCK"]/flow[@class="Block"]');
        foreach ($leftBlocks as $idx => $lb) {
            $txt = (string) ($lb->xpath('.//mat_formattedtext | .//mattext')[0] ?? '');
            $cleanRow = trim(strip_tags(html_entity_decode($txt, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if ($cleanRow !== '') {
                $rows[] = $cleanRow;
                $respLid = (string) ($lb->xpath('.//response_lid/@ident')[0] ?? '');
                $rowRespMap[count($rows) - 1] = $respLid;
            }
        }

        // 3. Columns (Right match targets)
        $rightBlockNodes = $item->xpath('.//flow[@class="RIGHT_MATCH_BLOCK"]/flow[@class="Block"]');
        $columns = [];
        $colIndexMap = []; // rightBlockIndex => uniqueColumnIndex
        foreach ($rightBlockNodes as $rIdx => $rb) {
            $cTxt = trim(strip_tags(html_entity_decode((string) ($rb->xpath('.//mat_formattedtext | .//mattext')[0] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if ($cTxt !== '') {
                if (! in_array($cTxt, $columns, true)) {
                    $columns[] = $cTxt;
                }
                $colIndex = array_search($cTxt, $columns, true);
                $colIndexMap[$rIdx] = $colIndex;
            }
        }

        // 4. Correct match conditions from resprocessing
        $correctMatches = [];
        $respConditions = $item->xpath('.//resprocessing//respcondition[not(contains(@title, "incorrect"))]//varequal');
        foreach ($respConditions as $ve) {
            $leftId = (string) $ve['respident'];
            $rightId = trim((string) $ve);
            if ($leftId !== '' && $rightId !== '') {
                $correctMatches[$leftId] = $rightId;
            }
        }

        // 5. Build answer mapping: [rowIndex => columnIndex]
        $answer = [];
        foreach ($rowRespMap as $rIdx => $respLid) {
            if (isset($correctMatches[$respLid])) {
                $matchedTargetId = $correctMatches[$respLid];
                $allLabels = $item->xpath('.//response_lid[@ident="'.$respLid.'"]//response_label');
                foreach ($allLabels as $pos => $lbl) {
                    if ((string) $lbl['ident'] === $matchedTargetId) {
                        if (isset($colIndexMap[$pos])) {
                            $answer[$rIdx] = $colIndexMap[$pos];
                        }
                        break;
                    }
                }
            }
        }

        // Points
        $points = 10;
        $qmdWeight = $item->xpath('.//itemmetadata/qmd_weighting | .//itemmetadata/qmd_absolutescore_max')[0] ?? null;
        if ($qmdWeight && (float) $qmdWeight > 0) {
            $points = (int) round((float) $qmdWeight);
        }

        return [
            'id' => $nextId++,
            'title' => $title,
            'description' => '',
            'type' => 'Multiple-choice grid',
            'options' => [],
            'rows' => $rows,
            'columns' => $columns,
            'answer' => (object) $answer,
            'required' => false,
            'media' => $media,
            'points' => $points,
        ];
    }
}
