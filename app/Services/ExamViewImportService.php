<?php

namespace App\Services;

use App\Support\HtmlToPlainText;
use App\Support\MediaUrl;
use DOMDocument;
use DOMElement;
use DOMText;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;
use ZipArchive;

/**
 * Import question banks exported by ExamView Test Generator for Blackboard.
 *
 * Supported packages (IMS content package with imsmanifest.xml):
 *  - "Blackboard 6.0 - 7.0": resource type assessment/x-bb-pool, Blackboard POOL XML
 *    (<QUESTION_MULTIPLECHOICE>, <QUESTION_TRUEFALSE>, ...).
 *  - "Blackboard 7.1 - 9.x": resource types assessment/x-bb-qti-test and assessment/x-bb-qti-pool,
 *    Blackboard flavoured IMS QTI 1.2 (<questestinterop>/<item>).
 *
 * Images may be referenced as @X@EmbeddedFile.location@X@file.gif (stored next to the resource,
 * e.g. res00001/file.gif), @X@EmbeddedFile.requestUrlStub@X@bbcswebdav/xid-123_1 (Blackboard 9
 * csfiles/home_dir/name__xid-123_1.gif), plain relative paths, <matimage uri> or data URIs.
 */
class ExamViewImportService
{
    /**
     * Points used when the export does not define a score for a question.
     */
    protected const DEFAULT_POINTS = 10;

    /**
     * Question numbers start here so they never collide with questions already in the editor.
     */
    protected const FIRST_QUESTION_ID = 1000;

    /**
     * @var list<string>
     */
    protected array $warnings = [];

    /**
     * Normalized zip path => original entry name.
     *
     * @var array<string, string>
     */
    protected array $entries = [];

    /**
     * Lowercase normalized zip path => original entry name.
     *
     * @var array<string, string>
     */
    protected array $entriesLowercase = [];

    /**
     * Lowercase file name => original entry names.
     *
     * @var array<string, list<string>>
     */
    protected array $entriesByBasename = [];

    /**
     * Stored image URL per image content hash (an image used twice is stored once).
     *
     * @var array<string, string>
     */
    protected array $storedImages = [];

    protected ?ZipArchive $zip = null;

    /**
     * Number of the question being parsed, used in warnings.
     */
    protected int $questionNumber = 0;

    public function __construct(
        protected DocxImportService $docxImportService = new DocxImportService,
        protected MediaUrl $mediaUrl = new MediaUrl,
        protected ImageOptimizationService $imageOptimizer = new ImageOptimizationService,
        protected HtmlToPlainText $htmlToText = new HtmlToPlainText,
    ) {}

    /**
     * Problems found during the last import (missing images, unsupported question types, ...).
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * Parse an uploaded ExamView Blackboard export ZIP archive (or ZIP containing DOCX).
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws \RuntimeException
     */
    public function parseZip(UploadedFile|string $file): array
    {
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        $this->warnings = [];
        $this->storedImages = [];
        $this->questionNumber = 0;

        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException('Berkas ZIP tidak dapat dibuka atau berkas rusak.');
        }

        $this->zip = $zip;

        try {
            $this->indexEntries();

            // Check if user accidentally zipped a raw .bnk file instead of exporting
            foreach ($this->entries as $path => $entry) {
                if (str_ends_with(strtolower($path), '.bnk')) {
                    throw new \RuntimeException('Berkas ZIP memuat berkas .bnk mentah ('.basename($entry).'). Alsenform membutuhkan file ZIP hasil ekspor ExamView. Silakan buka bank soal di aplikasi ExamView Test Generator, klik menu File -> Export -> Blackboard 7.1-9.0 (atau Blackboard 6.0-7.0), lalu unggah file ZIP hasil ekspor tersebut.');
                }
            }

            $resources = $this->discoverResources();

            if ($resources === []) {
                // Check if user uploaded a ZIP containing a Word document (.docx)
                $docxFiles = $this->findDocxFiles();
                if ($docxFiles !== []) {
                    return $this->parseDocxFromZip($docxFiles);
                }

                $foundFiles = array_slice(array_map('basename', array_values($this->entries)), 0, 6);
                $fileHint = $foundFiles !== [] ? ' (berkas yang ditemukan di dalam ZIP: '.implode(', ', $foundFiles).')' : '';

                throw new \RuntimeException('Berkas ZIP tidak memuat data bank soal ExamView / Blackboard yang valid'.$fileHint.'. Pastikan mengekspor dengan format Blackboard 7.1-9.0 atau Blackboard 6.0-7.0 dari ExamView Test Generator, atau unggah dokumen Word (.docx).');
            }

            $questions = [];
            $seenSignatures = [];
            $nextId = self::FIRST_QUESTION_ID;

            foreach ($resources as $resource) {
                foreach ($this->parseResource($resource) as $question) {
                    // ExamView may export the same questions both as a test and as a pool.
                    $signature = md5((string) json_encode([$question['type'], $question['title'], $question['options'], $question['rows'], $question['columns']]));
                    if (isset($seenSignatures[$signature]) && $seenSignatures[$signature] !== $resource['path']) {
                        continue;
                    }
                    $seenSignatures[$signature] = $resource['path'];

                    $question['id'] = $nextId++;
                    $questions[] = $question;
                }
            }

            if ($questions === []) {
                throw new \RuntimeException('Tidak ditemukan pertanyaan yang dapat diurai di dalam berkas bank soal. Pastikan berkas ZIP berisi butir soal pilihan ganda, benar/salah, atau esai.');
            }

            return $questions;
        } finally {
            $zip->close();
            $this->zip = null;
        }
    }

    /**
     * Index every file of the archive by normalized path and by file name.
     */
    protected function indexEntries(): void
    {
        $this->entries = [];
        $this->entriesLowercase = [];
        $this->entriesByBasename = [];

        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $name = (string) $this->zip->getNameIndex($i);
            if ($name === '' || str_ends_with($name, '/') || str_ends_with($name, '\\')) {
                continue;
            }

            $path = $this->normalizePath($name);
            if ($path === '' || str_starts_with($path, '__MACOSX/') || str_starts_with(basename($path), '._')) {
                continue;
            }

            $this->entries[$path] = $name;
            $this->entriesLowercase[strtolower($path)] = $name;
            $this->entriesByBasename[strtolower(basename($path))][] = $name;
        }
    }

    /**
     * Find the question data files of the package.
     *
     * @return list<array{path: string, entry: string, base: string, type: string}>
     */
    protected function discoverResources(): array
    {
        $resources = [];

        foreach ($this->manifestResources() as $resource) {
            $resources[$resource['path']] = $resource;
        }

        // Packages without (usable) manifest: detect question files by their content.
        if ($resources === []) {
            foreach ($this->entries as $path => $entry) {
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if (! in_array($extension, ['dat', 'xml', 'qti', 'txt', ''], true) || strcasecmp(basename($path), 'imsmanifest.xml') === 0) {
                    continue;
                }

                $content = (string) $this->zip->getFromName($entry);
                if ($this->looksLikeQuestionData($content)) {
                    $resources[$path] = [
                        'path' => $path,
                        'entry' => $entry,
                        'base' => $this->defaultResourceBase($path, pathinfo($path, PATHINFO_FILENAME)),
                        'type' => '',
                    ];
                }
            }
        }

        return array_values($resources);
    }

    /**
     * Assessment resources listed in imsmanifest.xml.
     *
     * @return list<array{path: string, entry: string, base: string, type: string}>
     */
    protected function manifestResources(): array
    {
        $manifestPath = null;
        foreach (array_keys($this->entries) as $path) {
            if (strcasecmp(basename($path), 'imsmanifest.xml') === 0 && ($manifestPath === null || substr_count($path, '/') < substr_count($manifestPath, '/'))) {
                $manifestPath = $path;
            }
        }

        if ($manifestPath === null) {
            return [];
        }

        $document = new DOMDocument;
        $xml = $this->decodeXmlBytes((string) $this->zip->getFromName($this->entries[$manifestPath]));
        if (! @$document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | (defined('LIBXML_PARSEHUGE') ? LIBXML_PARSEHUGE : 0))) {
            return [];
        }

        $manifestDir = $this->directoryOf($manifestPath);
        $resources = [];

        foreach ($document->getElementsByTagNameNS('*', 'resource') as $element) {
            $type = strtolower($this->attributeByLocalName($element, ['type']));
            $identifier = $this->attributeByLocalName($element, ['identifier']);

            if (str_contains($type, 'survey')) {
                $this->warnings[] = 'Survei Blackboard ('.$identifier.') dilewati karena tidak memiliki kunci jawaban.';

                continue;
            }

            if ($type !== '' && ! str_starts_with($type, 'assessment/') && ! str_contains($type, 'qti') && ! str_contains($type, 'pool')) {
                continue;
            }

            $file = $this->attributeByLocalName($element, ['file', 'href']);
            if ($file === '') {
                foreach ($element->getElementsByTagNameNS('*', 'file') as $fileElement) {
                    $file = $this->attributeByLocalName($fileElement, ['href']);
                    if ($file !== '') {
                        break;
                    }
                }
            }

            if ($file === '') {
                continue;
            }

            $path = $this->locateEntry($this->joinPath($manifestDir, $file)) ?? $this->locateEntry($file);
            if ($path === null) {
                continue;
            }

            $content = (string) $this->zip->getFromName($this->entries[$path]);
            if (! $this->looksLikeQuestionData($content)) {
                continue;
            }

            $base = $this->attributeByLocalName($element, ['base', 'baseurl']);
            $resources[] = [
                'path' => $path,
                'entry' => $this->entries[$path],
                'base' => $base !== '' ? $this->joinPath($manifestDir, $base) : $this->defaultResourceBase($path, $identifier),
                'type' => $type,
            ];
        }

        return $resources;
    }

    /**
     * Determine if a file contains Blackboard / QTI question data.
     */
    protected function looksLikeQuestionData(string $content): bool
    {
        return stripos($content, '<questestinterop') !== false
            || preg_match('/<(?:\w+:)?item[\s>]/i', $content) === 1
            || stripos($content, '<POOL') !== false
            || preg_match('/<QUESTION(?:_[A-Z]+)?[\s>]/i', $content) === 1;
    }

    /**
     * Directory holding the embedded files of a resource (res00001.dat => res00001/).
     */
    protected function defaultResourceBase(string $resourcePath, string $identifier): string
    {
        $directory = $this->directoryOf($resourcePath);
        $candidate = $this->joinPath($directory, $identifier !== '' ? $identifier : pathinfo($resourcePath, PATHINFO_FILENAME));

        foreach (array_keys($this->entries) as $path) {
            if (str_starts_with(strtolower($path), strtolower($candidate).'/')) {
                return $candidate;
            }
        }

        return $directory;
    }

    /**
     * Parse one question data file.
     *
     * @param  array{path: string, entry: string, base: string, type: string}  $resource
     * @return list<array<string, mixed>>
     */
    protected function parseResource(array $resource): array
    {
        $root = $this->loadXml((string) $this->zip->getFromName($resource['entry']));

        if ($root === null) {
            Log::warning('ExamView import could not parse '.$resource['path']);
            $this->warnings[] = 'Berkas '.basename($resource['path']).' tidak dapat dibaca (XML rusak).';

            return [];
        }

        if ($root->xpath('//item')) {
            return $this->parseQtiItems($root, $resource['base']);
        }

        return $this->parsePoolQuestions($root, $resource['base']);
    }

    // ---------------------------------------------------------------------------------------------
    // Blackboard 7.1 - 9.x: IMS QTI 1.2 (<questestinterop>)
    // ---------------------------------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    protected function parseQtiItems(SimpleXMLElement $root, string $baseDir): array
    {
        $questions = [];

        foreach ($root->xpath('//item') ?: [] as $item) {
            $this->questionNumber++;
            $question = $this->parseQtiItem($item, $baseDir);
            if ($question !== null) {
                $questions[] = $question;
            }
        }

        return $questions;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function parseQtiItem(SimpleXMLElement $item, string $baseDir): ?array
    {
        $bbType = trim((string) ($this->first($item, 'itemmetadata/bbmd_questiontype | itemmetadata/qmd_itemtype | itemmetadata/qmd_questiontype') ?? ''));
        $kind = $this->questionKind($bbType) ?? $this->inferQtiKind($item);

        $media = [];
        $title = $this->qtiPrompt($item, $baseDir, $media);
        $points = $this->qtiPoints($item);

        return match ($kind) {
            'matching' => $this->qtiMatching($item, $baseDir, $title, $media, $points),
            'ordering' => $this->qtiOrdering($item, $baseDir, $title, $media, $points),
            'fill' => $this->qtiFillInTheBlank($item, $title, $media, $points),
            'fillplus' => $this->qtiFillInTheBlankPlus($item, $title, $media, $points),
            'numeric' => $this->qtiNumeric($item, $title, $media, $points),
            'essay' => $this->qtiEssay($item, $baseDir, $title, $media, $points),
            'unsupported' => $this->unsupported($bbType, $title, $media, $points),
            default => $this->qtiChoice($item, $baseDir, $kind, $title, $media, $points),
        };
    }

    /**
     * Guess the question kind from the response structure (non-Blackboard QTI).
     */
    protected function inferQtiKind(SimpleXMLElement $item): string
    {
        if ($this->first($item, 'presentation//flow[@class="RIGHT_MATCH_BLOCK"]')) {
            return 'matching';
        }

        $responseLid = $this->first($item, 'presentation//response_lid');
        if ($responseLid !== null) {
            return match (strtolower((string) $responseLid['rcardinality'])) {
                'multiple' => 'multipleanswer',
                'ordered' => 'ordering',
                default => 'multiplechoice',
            };
        }

        if ($this->first($item, 'presentation//response_num')) {
            return 'numeric';
        }

        if ($this->first($item, 'presentation//response_str')) {
            return $this->first($item, 'resprocessing//varequal') ? 'fill' : 'essay';
        }

        return 'essay';
    }

    /**
     * Question text and images from the QUESTION_BLOCK (or the presentation material).
     *
     * @param  list<array<string, string>>  $media
     */
    protected function qtiPrompt(SimpleXMLElement $item, string $baseDir, array &$media): string
    {
        $materials = $item->xpath('presentation//flow[@class="QUESTION_BLOCK"]//material');

        if (! $materials) {
            $materials = $item->xpath('presentation//material[not(ancestor::response_lid) and not(ancestor::response_str) and not(ancestor::response_num) and not(ancestor::response_grp) and not(ancestor::flow[@class="RESPONSE_BLOCK"]) and not(ancestor::flow[@class="RIGHT_MATCH_BLOCK"])]') ?: [];
        }

        $text = $this->renderMaterials($materials, $baseDir, $media);

        return $this->finalTitle($text, $media, trim((string) ($item['title'] ?? '')));
    }

    /**
     * Multiple choice, true/false, either/or, multiple answer and opinion scale questions.
     *
     * @param  list<array<string, string>>  $media
     * @return array<string, mixed>
     */
    protected function qtiChoice(SimpleXMLElement $item, string $baseDir, string $kind, string $title, array $media, int $points): array
    {
        $labels = $item->xpath('presentation//response_lid//response_label') ?: [];
        $choices = [];

        foreach ($labels as $index => $label) {
            $choices[] = [
                'ident' => (string) $label['ident'],
                'text' => $this->renderMaterials($label->xpath('.//material') ?: [], $baseDir, $media, $this->optionLetter($index)),
            ];
        }

        if ($choices === [] && ! in_array($kind, ['truefalse', 'eitheror'], true)) {
            $this->warn('pilihan jawaban tidak ditemukan, diimpor sebagai soal uraian.');

            return $this->question('Paragraph', $title, $media, $points);
        }

        $options = $this->optionTexts($kind, $choices);
        $positive = $this->qtiPositiveIdents($item);

        if ($kind === 'multipleanswer') {
            $correct = [];
            foreach ($choices as $index => $choice) {
                if (in_array($choice['ident'], $positive, true)) {
                    $correct[] = $options[$index];
                }
            }

            if ($correct === []) {
                $this->warn('kunci jawaban tidak ditemukan, silakan pilih jawaban benar secara manual.');
            }

            return $this->question('Checkboxes', $title, $media, $points, options: $options, answer: $correct);
        }

        if ($kind === 'opinion') {
            return $this->question('Multiple choice', $title, $media, $points, options: $options, answer: '');
        }

        if ($options === [] && $kind === 'truefalse') {
            $options = ['Benar', 'Salah'];
            $choices = [['ident' => 'true', 'text' => 'true'], ['ident' => 'false', 'text' => 'false']];
        }

        $answer = '';
        foreach ($positive as $ident) {
            foreach ($choices as $index => $choice) {
                if (strcasecmp($choice['ident'], $ident) === 0) {
                    $answer = $index;
                    break 2;
                }
            }

            if ($kind === 'truefalse' && in_array(strtolower($ident), ['true', 'false'], true)) {
                $answer = strtolower($ident) === 'true' ? 0 : 1;
                break;
            }
        }

        if ($answer === '') {
            $this->warn('kunci jawaban tidak ditemukan, silakan pilih jawaban benar secara manual.');
        }

        return $this->question('Multiple choice', $title, $media, $points, options: $options, answer: $answer);
    }

    /**
     * @param  list<array<string, string>>  $media
     * @return array<string, mixed>
     */
    protected function qtiFillInTheBlank(SimpleXMLElement $item, string $title, array $media, int $points): array
    {
        $answers = [];
        foreach ($this->qtiCorrectConditions($item) as $condition) {
            foreach ($condition->xpath('conditionvar//*[(local-name()="varequal" or local-name()="varsubstring") and not(ancestor::not)]') ?: [] as $value) {
                $answers[] = trim((string) $value);
            }
        }

        $answers = array_values(array_unique(array_filter($answers, fn (string $answer) => $answer !== '')));
        if ($answers === []) {
            $this->warn('jawaban isian tidak ditemukan, silakan isi kunci jawaban secara manual.');
        }

        return $this->question('Short answer', $title, $media, $points, answer: implode(' | ', $answers));
    }

    /**
     * Fill in multiple blanks has no equivalent: keep the question as an essay with the key as reference.
     *
     * @param  list<array<string, string>>  $media
     * @return array<string, mixed>
     */
    protected function qtiFillInTheBlankPlus(SimpleXMLElement $item, string $title, array $media, int $points): array
    {
        $blanks = [];
        foreach ($item->xpath('resprocessing//varequal[not(ancestor::not)]') ?: [] as $value) {
            $blank = (string) $value['respident'];
            $text = trim((string) $value);
            if ($blank !== '' && $text !== '') {
                $blanks[$blank][] = $text;
            }
        }

        $key = implode('; ', array_map(fn (string $blank, array $values) => $blank.': '.implode(' | ', array_unique($values)), array_keys($blanks), $blanks));
        $this->warn('isian ganda (Fill in Multiple Blanks) diimpor sebagai soal uraian dan dinilai manual.');

        return $this->question('Paragraph', $title, $media, $points, answer: $key);
    }

    /**
     * @param  list<array<string, string>>  $media
     * @return array<string, mixed>
     */
    protected function qtiNumeric(SimpleXMLElement $item, string $title, array $media, int $points): array
    {
        // Blackboard keeps the exact answer and the tolerance range in separate conditions.
        $conditions = array_filter(
            $item->xpath('resprocessing/respcondition') ?: [],
            fn (SimpleXMLElement $condition) => strcasecmp(trim((string) $condition['title']), 'incorrect') !== 0,
        );
        $exact = null;
        $minimum = null;
        $maximum = null;

        foreach ($conditions as $condition) {
            $exact ??= $this->numericValue($this->first($condition, 'conditionvar//varequal[not(ancestor::not)]'));
            $minimum ??= $this->numericValue($this->first($condition, 'conditionvar//vargte | conditionvar//vargt'));
            $maximum ??= $this->numericValue($this->first($condition, 'conditionvar//varlte | conditionvar//varlt'));
        }

        $answer = match (true) {
            $minimum !== null && $maximum !== null && $minimum !== $maximum => $minimum.'..'.$maximum,
            $exact !== null => $exact,
            $minimum !== null => $minimum,
            default => '',
        };

        if ($answer === '') {
            $this->warn('kunci jawaban numerik tidak ditemukan, silakan isi secara manual.');
        }

        return $this->question('Short answer', $title, $media, $points, answer: $answer);
    }

    /**
     * @param  list<array<string, string>>  $media
     * @return array<string, mixed>
     */
    protected function qtiEssay(SimpleXMLElement $item, string $baseDir, string $title, array $media, int $points): array
    {
        $solutionMedia = [];
        $sample = $this->renderMaterials(
            $item->xpath('itemfeedback[@ident="solution"]//material | itemfeedback//solution//material') ?: [],
            $baseDir,
            $solutionMedia,
        );

        return $this->question('Paragraph', $title, $media, $points, answer: $sample);
    }

    /**
     * Matching: premises become grid rows, the answer list becomes the grid columns.
     *
     * @param  list<array<string, string>>  $media
     * @return array<string, mixed>
     */
    protected function qtiMatching(SimpleXMLElement $item, string $baseDir, string $title, array $media, int $points): array
    {
        $rows = [];
        $rowLabelIdents = [];
        $rowResponseIdents = [];

        foreach ($item->xpath('presentation//flow[@class="RESPONSE_BLOCK"]/flow') ?: [] as $index => $block) {
            $responseLid = $this->first($block, './/response_lid');
            if ($responseLid === null) {
                continue;
            }

            $text = $this->renderMaterials(
                $block->xpath('.//material[not(ancestor::response_lid)]') ?: [],
                $baseDir,
                $media,
                'pernyataan '.($index + 1),
            );

            $rows[] = $text !== '' ? $text : 'Pernyataan '.($index + 1);
            $rowResponseIdents[] = (string) $responseLid['ident'];
            $rowLabelIdents[] = array_map(fn (SimpleXMLElement $label) => (string) $label['ident'], $responseLid->xpath('.//response_label') ?: []);
        }

        $columns = [];
        foreach ($item->xpath('presentation//flow[@class="RIGHT_MATCH_BLOCK"]/flow') ?: [] as $index => $block) {
            $text = $this->renderMaterials($block->xpath('.//material') ?: [], $baseDir, $media, 'jawaban '.$this->optionLetter($index));
            $columns[] = $text !== '' ? $text : 'Jawaban '.$this->optionLetter($index);
        }

        if ($columns === [] && $rows !== []) {
            // Some exports keep the answer texts inside the response labels of the first premise.
            foreach ($item->xpath('presentation//flow[@class="RESPONSE_BLOCK"]//response_lid[1]//response_label') ?: [] as $index => $label) {
                $text = $this->renderMaterials($label->xpath('.//material') ?: [], $baseDir, $media, 'jawaban '.$this->optionLetter($index));
                $columns[] = $text !== '' ? $text : 'Jawaban '.$this->optionLetter($index);
            }
        }

        $correctLabels = [];
        foreach ($item->xpath('resprocessing/respcondition') ?: [] as $condition) {
            if (strcasecmp(trim((string) $condition['title']), 'incorrect') === 0) {
                continue;
            }

            foreach ($condition->xpath('conditionvar//varequal[not(ancestor::not)]') ?: [] as $value) {
                $respident = (string) $value['respident'];
                if ($respident !== '' && trim((string) $value) !== '') {
                    $correctLabels[$respident] ??= trim((string) $value);
                }
            }
        }

        $answer = [];
        foreach ($rowResponseIdents as $rowIndex => $responseIdent) {
            $label = $correctLabels[$responseIdent] ?? null;
            $columnIndex = $label === null ? false : array_search($label, $rowLabelIdents[$rowIndex], true);

            if ($columnIndex !== false && isset($columns[$columnIndex])) {
                $answer[$rowIndex] = $columnIndex;
            }
        }

        if (count($answer) < count($rows)) {
            $this->warn('sebagian pasangan jawaban menjodohkan tidak ditemukan, periksa kunci jawabannya.');
        }

        return $this->question('Multiple-choice grid', $title, $media, $points, rows: $rows, columns: $columns, answer: (object) $answer);
    }

    /**
     * Ordering: each item is a row, the columns are the positions 1..n.
     *
     * @param  list<array<string, string>>  $media
     * @return array<string, mixed>
     */
    protected function qtiOrdering(SimpleXMLElement $item, string $baseDir, string $title, array $media, int $points): array
    {
        $rows = [];
        $idents = [];

        foreach ($item->xpath('presentation//response_lid//response_label') ?: [] as $index => $label) {
            $text = $this->renderMaterials($label->xpath('.//material') ?: [], $baseDir, $media, 'urutan '.$this->optionLetter($index));
            $rows[] = $text !== '' ? $text : 'Bagian '.$this->optionLetter($index);
            $idents[] = (string) $label['ident'];
        }

        $sequence = $this->qtiPositiveIdents($item);

        return $this->orderingQuestion($title, $media, $points, $rows, $idents, $sequence);
    }

    /**
     * Ident values of the responses that earn the score.
     *
     * @return list<string>
     */
    protected function qtiPositiveIdents(SimpleXMLElement $item): array
    {
        $idents = [];
        foreach ($this->qtiCorrectConditions($item) as $condition) {
            foreach ($condition->xpath('conditionvar//varequal[not(ancestor::not)]') ?: [] as $value) {
                $idents[] = trim((string) $value);
            }
        }

        // Blackboard 9 also scores every answer separately: <varequal respident="ANSWER_ID"/> with 100%.
        if (array_filter($idents) === []) {
            foreach ($item->xpath('resprocessing/respcondition') ?: [] as $condition) {
                if ($this->conditionScore($condition) >= 100) {
                    foreach ($condition->xpath('conditionvar//varequal[not(ancestor::not)]') ?: [] as $value) {
                        $idents[] = trim((string) $value) !== '' ? trim((string) $value) : (string) $value['respident'];
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($idents, fn (string $ident) => $ident !== '')));
    }

    /**
     * The response conditions describing the correct answer.
     *
     * @return list<SimpleXMLElement>
     */
    protected function qtiCorrectConditions(SimpleXMLElement $item): array
    {
        $conditions = $item->xpath('resprocessing/respcondition') ?: [];

        $titled = array_values(array_filter($conditions, fn (SimpleXMLElement $condition) => strcasecmp(trim((string) $condition['title']), 'correct') === 0));
        if ($titled !== []) {
            return $titled;
        }

        return array_values(array_filter($conditions, fn (SimpleXMLElement $condition) => strcasecmp(trim((string) $condition['title']), 'incorrect') !== 0
            && $this->conditionScore($condition) > 0
            && $condition->xpath('conditionvar//varequal[not(ancestor::not)]')));
    }

    /**
     * Score granted by a response condition (SCORE.max counts as 100).
     */
    protected function conditionScore(SimpleXMLElement $condition): float
    {
        $value = trim((string) ($this->first($condition, 'setvar') ?? ''));

        if (stripos($value, '.max') !== false) {
            return 100.0;
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * Points of a QTI item.
     */
    protected function qtiPoints(SimpleXMLElement $item): int
    {
        $candidates = [
            $this->first($item, 'itemmetadata/qmd_absolutescore_max'),
            $this->first($item, 'itemmetadata/qmd_weighting'),
            $this->first($item, 'resprocessing/outcomes/decvar/@maxvalue'),
        ];

        foreach ($this->qtiCorrectConditions($item) as $condition) {
            $candidates[] = $this->first($condition, 'setvar');
        }
        $candidates[] = $this->first($item, 'resprocessing/setvar');

        foreach ($candidates as $candidate) {
            $value = trim((string) ($candidate ?? ''));
            if (is_numeric($value) && (float) $value > 0) {
                return max(1, (int) round((float) $value));
            }
        }

        return self::DEFAULT_POINTS;
    }

    // ---------------------------------------------------------------------------------------------
    // Blackboard 6.0 - 7.0: POOL format (<QUESTION_MULTIPLECHOICE>, ...)
    // ---------------------------------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    protected function parsePoolQuestions(SimpleXMLElement $root, string $baseDir): array
    {
        $nodes = $root->xpath('//*[starts-with(local-name(), "QUESTION_") or ((local-name()="QUESTION" or local-name()="question") and (@type or BODY or body or ANSWER or answer))]') ?: [];

        if ($nodes === [] && preg_match('/^question/i', $root->getName())) {
            $nodes = [$root];
        }

        $questions = [];
        foreach ($nodes as $node) {
            if (in_array(strtoupper($node->getName()), ['QUESTIONLIST'], true)) {
                continue;
            }

            $this->questionNumber++;
            $question = $this->parsePoolQuestion($node, $baseDir);
            if ($question !== null) {
                $questions[] = $question;
            }
        }

        return $questions;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function parsePoolQuestion(SimpleXMLElement $node, string $baseDir): ?array
    {
        $name = strtoupper($node->getName());
        $typeName = str_starts_with($name, 'QUESTION_')
            ? substr($name, strlen('QUESTION_'))
            : (string) ($node['type'] ?? $this->first($node, 'DATED/@type | dated/@type') ?? '');

        $kind = $this->questionKind($typeName) ?? 'multiplechoice';

        $media = [];
        $title = $this->finalTitle($this->poolBodyText($node, $baseDir, $media), $media, (string) ($node['title'] ?? ''));
        $points = $this->poolPoints($node);

        $answers = [];
        foreach ($node->xpath('ANSWER | answer') ?: [] as $index => $answerNode) {
            $answers[] = ['node' => $answerNode, 'position' => is_numeric((string) $answerNode['position']) ? (int) $answerNode['position'] : $index + 1, 'index' => $index];
        }
        usort($answers, fn (array $a, array $b) => [$a['position'], $a['index']] <=> [$b['position'], $b['index']]);

        $correctNodes = $node->xpath('GRADABLE//CORRECTANSWER | gradable//correctanswer | CORRECTANSWER | correctanswer | CORRECT_ANSWER | correct_answer') ?: [];
        $correctIds = array_values(array_filter(array_map(
            fn (SimpleXMLElement $correct) => (string) ($correct['answer_id'] ?? $correct['id'] ?? $correct['ident'] ?? '') ?: trim((string) $correct),
            $correctNodes,
        )));

        if ($kind === 'matching') {
            return $this->poolMatching($node, $baseDir, $title, $media, $points, $answers, $correctNodes);
        }

        if ($kind === 'unsupported') {
            return $this->unsupported($typeName, $title, $media, $points);
        }

        $choices = [];
        foreach ($answers as $index => $answer) {
            $choices[] = [
                'ident' => (string) ($answer['node']['id'] ?? $answer['node']['ident'] ?? $answer['node']['answer_id'] ?? ''),
                'text' => $this->poolText($this->first($answer['node'], 'TEXT | text') ?? $answer['node'], $baseDir, $media, true, $this->optionLetter($index)),
            ];
        }

        if ($kind === 'ordering') {
            $rows = array_map(fn (array $choice) => $choice['text'], $choices);
            $sequence = count($correctIds) > 1 ? $correctIds : array_map(fn (array $choice) => $choice['ident'], $choices);

            return $this->orderingQuestion($title, $media, $points, $rows, array_map(fn (array $choice) => $choice['ident'], $choices), $sequence);
        }

        if (in_array($kind, ['fill', 'numeric'], true)) {
            $values = $correctNodes !== [] && trim((string) $correctNodes[0]) !== ''
                ? array_map(fn (SimpleXMLElement $correct) => trim((string) $correct), $correctNodes)
                : array_map(fn (array $choice) => $choice['text'], $choices);
            $values = array_values(array_unique(array_filter($values, fn (string $value) => $value !== '')));

            if ($values === []) {
                $this->warn('jawaban isian tidak ditemukan, silakan isi kunci jawaban secara manual.');
            }

            return $this->question('Short answer', $title, $media, $points, answer: implode(' | ', $values));
        }

        if ($kind === 'essay') {
            $sample = $choices[0]['text'] ?? ($correctNodes !== [] ? trim((string) $correctNodes[0]) : '');

            return $this->question('Paragraph', $title, $media, $points, answer: $sample);
        }

        $options = $this->optionTexts($kind, $choices);

        if ($kind === 'truefalse' && $options === []) {
            $options = ['Benar', 'Salah'];
        }

        if ($kind === 'opinion') {
            return $this->question('Multiple choice', $title, $media, $points, options: $options, answer: '');
        }

        if ($kind === 'multipleanswer') {
            $correct = [];
            foreach ($choices as $index => $choice) {
                if (in_array($choice['ident'], $correctIds, true)) {
                    $correct[] = $options[$index];
                }
            }

            if ($correct === []) {
                $this->warn('kunci jawaban tidak ditemukan, silakan pilih jawaban benar secara manual.');
            }

            return $this->question('Checkboxes', $title, $media, $points, options: $options, answer: $correct);
        }

        $answer = '';
        foreach ($correctIds as $correctId) {
            foreach ($choices as $index => $choice) {
                if ($choice['ident'] !== '' && $choice['ident'] === $correctId) {
                    $answer = $index;
                    break 2;
                }
            }

            if (preg_match('/^[A-Z]$/i', $correctId) && isset($options[ord(strtoupper($correctId)) - ord('A')])) {
                $answer = ord(strtoupper($correctId)) - ord('A');
                break;
            }

            if ($kind === 'truefalse' && in_array(strtolower($correctId), ['true', 'false', '1', '0'], true)) {
                $answer = in_array(strtolower($correctId), ['true', '1'], true) ? 0 : 1;
                break;
            }
        }

        if ($answer === '') {
            $this->warn('kunci jawaban tidak ditemukan, silakan pilih jawaban benar secara manual.');
        }

        return $this->question('Multiple choice', $title, $media, $points, options: $options, answer: $answer);
    }

    /**
     * Matching in the POOL format: ANSWER (left) is matched with CHOICE (right).
     *
     * @param  list<array<string, string>>  $media
     * @param  list<array{node: SimpleXMLElement, position: int, index: int}>  $answers
     * @param  list<SimpleXMLElement>  $correctNodes
     * @return array<string, mixed>
     */
    protected function poolMatching(SimpleXMLElement $node, string $baseDir, string $title, array $media, int $points, array $answers, array $correctNodes): array
    {
        $rows = [];
        $rowIds = [];
        foreach ($answers as $index => $answer) {
            $text = $this->poolText($this->first($answer['node'], 'TEXT | text') ?? $answer['node'], $baseDir, $media, true, 'pernyataan '.($index + 1));
            $rows[] = $text !== '' ? $text : 'Pernyataan '.($index + 1);
            $rowIds[] = (string) $answer['node']['id'];
        }

        $columns = [];
        $columnIds = [];
        foreach ($node->xpath('CHOICE | choice') ?: [] as $index => $choice) {
            $text = $this->poolText($this->first($choice, 'TEXT | text') ?? $choice, $baseDir, $media, true, 'jawaban '.$this->optionLetter($index));
            $columns[] = $text !== '' ? $text : 'Jawaban '.$this->optionLetter($index);
            $columnIds[] = (string) $choice['id'];
        }

        $answer = [];
        foreach ($correctNodes as $correct) {
            $rowIndex = array_search((string) $correct['answer_id'], $rowIds, true);
            $columnIndex = array_search((string) $correct['choice_id'], $columnIds, true);
            if ($rowIndex !== false && $columnIndex !== false) {
                $answer[$rowIndex] = $columnIndex;
            }
        }

        if (count($answer) < count($rows)) {
            $this->warn('sebagian pasangan jawaban menjodohkan tidak ditemukan, periksa kunci jawabannya.');
        }

        return $this->question('Multiple-choice grid', $title, $media, $points, rows: $rows, columns: $columns, answer: (object) $answer);
    }

    /**
     * Question text of a POOL question (BODY/TEXT plus attached images).
     *
     * @param  list<array<string, string>>  $media
     */
    protected function poolBodyText(SimpleXMLElement $node, string $baseDir, array &$media): string
    {
        $body = $this->first($node, 'BODY | body');
        $textNode = $body !== null ? $this->first($body, 'TEXT | text') : $this->first($node, 'PROMPT | prompt | TEXT | text');

        $isHtml = strtolower((string) ($body !== null ? ($this->first($body, 'FLAGS/ISHTML/@value | flags/ishtml/@value') ?? 'true') : 'true')) !== 'false';
        $text = $textNode !== null ? $this->poolText($textNode, $baseDir, $media, $isHtml) : '';

        // Attached files of the question body.
        foreach ($body?->xpath('IMAGE | image | FILE | file') ?: [] as $attachment) {
            $source = (string) ($attachment['value'] ?? $attachment['src'] ?? $attachment['name'] ?? '');
            if ($source !== '' && $this->isImagePath($source)) {
                $placeholder = $this->addImage($source, $baseDir, $media);
                $text .= $placeholder !== '' ? "\n".$placeholder : '';
            }
        }

        return trim($text);
    }

    /**
     * @param  list<array<string, string>>  $media
     */
    protected function poolText(SimpleXMLElement $node, string $baseDir, array &$media, bool $isHtml = true, string $captionNote = ''): string
    {
        $markup = $this->markupOf($node);

        if (! $isHtml) {
            return $this->htmlToText->plain($markup);
        }

        return $this->htmlToText->convert($markup, $this->imageCollector($baseDir, $media, $captionNote));
    }

    protected function poolPoints(SimpleXMLElement $node): int
    {
        $value = trim((string) ($this->first($node, 'GRADABLE/POINTS_POSSIBLE | gradable/points_possible | POINTS_POSSIBLE | points_possible | POINTS | points') ?? ''));

        return is_numeric($value) && (float) $value > 0 ? max(1, (int) round((float) $value)) : self::DEFAULT_POINTS;
    }

    // ---------------------------------------------------------------------------------------------
    // Shared helpers
    // ---------------------------------------------------------------------------------------------

    /**
     * Normalize a Blackboard / ExamView question type name.
     */
    protected function questionKind(string $type): ?string
    {
        $normalized = strtolower((string) preg_replace('/[^a-z]/i', '', $type));

        return match ($normalized) {
            '' => null,
            'multiplechoice', 'mc' => 'multiplechoice',
            'truefalse', 'tf', 'modifiedtruefalse' => 'truefalse',
            'eitheror', 'yesno' => 'eitheror',
            'multipleanswer', 'multipleresponse', 'ma' => 'multipleanswer',
            'fillintheblank', 'fillinblank', 'fib', 'completion', 'shortanswer' => 'fill',
            'fillintheblankplus', 'fillinmultipleblanks', 'fibplus' => 'fillplus',
            'numeric', 'numericresponse', 'calculatednumeric', 'num' => 'numeric',
            'essay', 'paragraph', 'ess', 'shortresponse', 'sr', 'problem', 'fileupload' => 'essay',
            'matching', 'match', 'mat' => 'matching',
            'ordering', 'order' => 'ordering',
            'opinionscale', 'likert', 'opinion' => 'opinion',
            'hotspot', 'jumbledsentence', 'quizbowl', 'calculated', 'calculatedformula' => 'unsupported',
            default => null,
        };
    }

    /**
     * Option labels shown to students.
     *
     * @param  list<array{ident: string, text: string}>  $choices
     * @return list<string>
     */
    protected function optionTexts(string $kind, array $choices): array
    {
        $eitherOrLabels = [
            'yes' => 'Ya', 'no' => 'Tidak',
            'agree' => 'Setuju', 'disagree' => 'Tidak Setuju',
            'right' => 'Benar', 'wrong' => 'Salah',
            'true' => 'Benar', 'false' => 'Salah',
        ];

        $options = [];
        foreach ($choices as $index => $choice) {
            $text = $choice['text'];
            $key = strtolower(trim((string) preg_replace('/^.*\./', '', $text !== '' ? $text : $choice['ident'])));

            if (in_array($kind, ['truefalse', 'eitheror'], true) && isset($eitherOrLabels[$key])) {
                $text = $eitherOrLabels[$key];
            } elseif ($kind === 'truefalse' && in_array($key, ['benar', 'salah'], true)) {
                $text = ucfirst($key);
            } elseif ($kind === 'truefalse' && count($choices) === 2) {
                // Blackboard: the first answer is "true", the second one "false".
                $text = $index === 0 ? 'Benar' : 'Salah';
            }

            if ($text === '') {
                $text = 'Pilihan '.$this->optionLetter($index);
            }

            // Keep options unique, the answer key of checkbox questions refers to the option text.
            $unique = $text;
            $suffix = 2;
            while (in_array($unique, $options, true)) {
                $unique = $text.' ('.$suffix++.')';
            }

            $options[] = $unique;
        }

        return $options;
    }

    /**
     * Build an ordering question as a grid of items x positions.
     *
     * @param  list<array<string, string>>  $media
     * @param  list<string>  $rows
     * @param  list<string>  $idents
     * @param  list<string>  $sequence
     * @return array<string, mixed>
     */
    protected function orderingQuestion(string $title, array $media, int $points, array $rows, array $idents, array $sequence): array
    {
        $columns = array_map(fn (int $position) => 'Urutan '.$position, range(1, max(1, count($rows))));

        $answer = [];
        foreach ($idents as $rowIndex => $ident) {
            $position = array_search($ident, $sequence, true);
            if ($position !== false && isset($columns[$position])) {
                $answer[$rowIndex] = $position;
            }
        }

        if (count($answer) < count($rows)) {
            $this->warn('urutan jawaban yang benar tidak lengkap, periksa kunci jawabannya.');
        }

        return $this->question('Multiple-choice grid', $title, $media, $points, rows: $rows, columns: $columns, answer: (object) $answer);
    }

    /**
     * @param  list<array<string, string>>  $media
     * @return array<string, mixed>
     */
    protected function unsupported(string $type, string $title, array $media, int $points): array
    {
        $this->warn('tipe soal "'.$type.'" belum didukung, diimpor sebagai soal uraian.');

        return $this->question('Paragraph', $title, $media, $points);
    }

    /**
     * @param  list<array<string, string>>  $media
     * @param  list<string>  $options
     * @param  list<string>  $rows
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    protected function question(string $type, string $title, array $media, int $points, array $options = [], array $rows = [], array $columns = [], mixed $answer = ''): array
    {
        return [
            'id' => 0,
            'title' => $title,
            'description' => '',
            'type' => $type,
            'options' => $options,
            'rows' => $rows,
            'columns' => $columns,
            'answer' => $answer,
            'required' => false,
            'media' => $media,
            'points' => $points,
        ];
    }

    /**
     * Render QTI <material> elements (text, formatted text, images) in document order.
     *
     * @param  list<SimpleXMLElement>  $materials
     * @param  list<array<string, string>>  $media
     */
    protected function renderMaterials(array $materials, string $baseDir, array &$media, string $captionNote = ''): string
    {
        $pieces = [];

        foreach ($materials as $material) {
            foreach ($material->xpath('.//mattext | .//mat_formattedtext | .//matemtext | .//matimage | .//matapplication | .//matbreak') ?: [] as $element) {
                $name = strtolower($element->getName());

                if ($name === 'matbreak') {
                    $pieces[] = '';

                    continue;
                }

                if (in_array($name, ['matimage', 'matapplication'], true)) {
                    $source = (string) ($element['uri'] ?? $element['URI'] ?? $element['label'] ?? '');
                    $embedded = trim((string) $element);

                    if ($source === '' && $embedded !== '' && strtolower((string) $element['embedded']) === 'base64') {
                        $source = 'data:'.((string) ($element['imagtype'] ?? 'image/png')).';base64,'.$embedded;
                    }

                    if ($source !== '' && ($name === 'matimage' || $this->isImagePath($source))) {
                        $pieces[] = $this->addImage($source, $baseDir, $media, $captionNote);
                    }

                    continue;
                }

                $markup = $this->markupOf($element);
                if (trim($markup) === '') {
                    continue;
                }

                $format = strtolower((string) ($element['type'] ?? $element['texttype'] ?? ''));
                $isHtml = str_contains($format, 'html') || $format === 'smart_text' || preg_match('/<\/?[a-z][a-z0-9]*[\s>\/]/i', $markup) === 1;

                if (! $isHtml) {
                    $pieces[] = $this->htmlToText->plain($markup);
                } else {
                    if ($format === 'smart_text') {
                        $markup = nl2br($markup, false);
                    }
                    $pieces[] = $this->htmlToText->convert($markup, $this->imageCollector($baseDir, $media, $captionNote));
                }
            }
        }

        // Some exports repeat the same text as plain and formatted text.
        $unique = [];
        foreach ($pieces as $piece) {
            if ($piece === '' || end($unique) !== $piece) {
                $unique[] = $piece;
            }
        }

        return trim((string) preg_replace("/\n{2,}/", "\n", implode("\n", $unique)));
    }

    /**
     * Callback for the HTML converter that adds every image to the question media.
     *
     * @param  list<array<string, string>>  $media
     * @return callable(string): string
     */
    protected function imageCollector(string $baseDir, array &$media, string $captionNote): callable
    {
        return function (string $source) use ($baseDir, &$media, $captionNote): string {
            return $this->addImage($source, $baseDir, $media, $captionNote);
        };
    }

    /**
     * Store an image of the question and return its "[Gambar n]" placeholder.
     *
     * @param  list<array<string, string>>  $media
     */
    protected function addImage(string $source, string $baseDir, array &$media, string $captionNote = ''): string
    {
        $url = $this->storeImage($source, $baseDir);
        if ($url === null) {
            return '';
        }

        foreach ($media as $index => $existing) {
            if ($existing['url'] === $url) {
                return '[Gambar '.($index + 1).']';
            }
        }

        $number = count($media) + 1;
        $media[] = [
            'type' => 'image',
            'url' => $url,
            'caption' => 'Gambar '.$number.($captionNote !== '' ? ' ('.$captionNote.')' : ''),
        ];

        return '[Gambar '.$number.']';
    }

    /**
     * Final question title: text with image placeholders, or a hint when the question is only an image.
     *
     * @param  list<array<string, string>>  $media
     */
    protected function finalTitle(string $text, array $media, string $fallback): string
    {
        $text = trim($text);

        if (trim((string) preg_replace('/\[Gambar \d+\]/', '', $text)) === '') {
            if ($media !== []) {
                return trim('Perhatikan gambar berikut. '.$text);
            }

            $this->warn('teks soal kosong.');

            return $fallback !== '' ? $fallback : 'Pertanyaan '.$this->questionNumber;
        }

        return $text;
    }

    /**
     * Resolve an image reference inside the package, store it and return its public URL.
     */
    protected function storeImage(string $source, string $baseDir): ?string
    {
        $source = trim(html_entity_decode($source, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($source === '') {
            return null;
        }

        if (preg_match('#^data:(image/[\w.+-]+);base64,(.+)$#is', $source, $matches)) {
            $bytes = base64_decode((string) preg_replace('/\s+/', '', $matches[2]), true);

            return $bytes ? $this->storeImageBytes($bytes, explode('/', $matches[1])[1] ?? 'png', 'gambar tersemat') : null;
        }

        if (preg_match('#^(https?:)?//#i', $source) && ! str_contains($source, '@X@')) {
            $this->warn('gambar '.$source.' berasal dari internet dan tidak akan tampil di jaringan intranet tanpa internet.');

            return $source;
        }

        $entry = $this->resolveEntry($source, $baseDir);
        if ($entry === null) {
            $this->warn('gambar "'.basename((string) preg_replace('/@X@[^@]*@X@/', '', $source)).'" tidak ditemukan di dalam ZIP.');

            return null;
        }

        $bytes = $this->zip->getFromName($entry);
        if (! $bytes) {
            $this->warn('gambar "'.basename($entry).'" kosong atau rusak.');

            return null;
        }

        return $this->storeImageBytes($bytes, pathinfo($entry, PATHINFO_EXTENSION), basename($entry));
    }

    protected function storeImageBytes(string $bytes, string $extension, string $name): ?string
    {
        $hash = md5($bytes);
        if (isset($this->storedImages[$hash])) {
            return $this->storedImages[$hash];
        }

        $mime = (string) ((new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '');
        $extension = strtolower($extension);

        if (in_array($extension, ['wmf', 'emf'], true) || in_array($mime, ['image/wmf', 'image/x-wmf', 'image/emf', 'image/x-emf', 'application/x-msmetafile'], true)) {
            $this->warn('gambar "'.$name.'" berformat WMF/EMF yang tidak dapat ditampilkan browser. Ubah gambar menjadi PNG/JPG di ExamView lalu ekspor ulang.');

            return null;
        }

        if (! str_starts_with($mime, 'image/')) {
            $this->warn('berkas "'.$name.'" bukan gambar yang valid.');

            return null;
        }

        if ($mime === 'image/tiff') {
            $this->warn('gambar "'.$name.'" berformat TIFF dan mungkin tidak tampil di Chrome/Android.');
        }

        $path = $this->imageOptimizer->optimizeAndStoreBytes($bytes, $extension !== '' ? $extension : 'png', 'media/examview', 'public');

        return $this->storedImages[$hash] = $this->mediaUrl->forPublicPath($path);
    }

    /**
     * Find the zip entry referenced by an image source.
     */
    protected function resolveEntry(string $reference, string $baseDir): ?string
    {
        $reference = (string) preg_replace('/@X@[^@]*@X@/', '', $reference);
        $reference = preg_split('/[?#]/', $reference)[0] ?? '';
        $reference = str_replace('\\', '/', trim($reference));
        $reference = (string) preg_replace('#^file:/+([a-z]:/)?#i', '', $reference);

        // Blackboard 9 content collection: bbcswebdav/xid-12345_1 => csfiles/home_dir/name__xid-12345_1.ext
        if (preg_match('/xid-(\d+_\d+)/i', $reference, $matches)) {
            foreach ($this->entries as $path => $entry) {
                if (preg_match('/xid-'.preg_quote($matches[1], '/').'(\.[^\/]*)?$/i', $path) && ! str_ends_with(strtolower($path), '.xml')) {
                    return $entry;
                }
            }
        }

        foreach (array_unique([$reference, rawurldecode($reference), urldecode($reference)]) as $variant) {
            $variant = ltrim((string) preg_replace('#^(\./)+#', '', $variant), '/');
            if ($variant === '') {
                continue;
            }

            foreach ([$this->joinPath($baseDir, $variant), $variant] as $candidate) {
                $path = $this->locateEntry($candidate);
                if ($path !== null) {
                    return $this->entries[$path];
                }
            }
        }

        $matches = $this->entriesByBasename[strtolower(basename(rawurldecode($reference)))] ?? [];
        if ($matches === []) {
            return null;
        }

        foreach ($matches as $entry) {
            if ($baseDir !== '' && str_starts_with(strtolower($this->normalizePath($entry)), strtolower($baseDir).'/')) {
                return $entry;
            }
        }

        return $matches[0];
    }

    /**
     * Normalized entry path for a (case-insensitive) path, or null.
     */
    protected function locateEntry(string $path): ?string
    {
        $path = $this->normalizePath($path);

        if (isset($this->entries[$path])) {
            return $path;
        }

        $entry = $this->entriesLowercase[strtolower($path)] ?? null;

        return $entry === null ? null : $this->normalizePath($entry);
    }

    protected function normalizePath(string $path): string
    {
        $segments = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    protected function joinPath(string $directory, string $path): string
    {
        return $this->normalizePath(trim($directory, '/') === '' ? $path : trim($directory, '/').'/'.$path);
    }

    protected function directoryOf(string $path): string
    {
        $directory = dirname($this->normalizePath($path));

        return $directory === '.' ? '' : $directory;
    }

    protected function isImagePath(string $path): bool
    {
        return (bool) preg_match('/\.(png|jpe?g|gif|bmp|webp|svg|tiff?|wmf|emf)(\?.*)?$/i', $path) || str_starts_with(strtolower($path), 'data:image/');
    }

    protected function optionLetter(int $index): string
    {
        return $index < 26 ? 'pilihan '.chr(ord('A') + $index) : 'pilihan '.($index + 1);
    }

    protected function numericValue(?SimpleXMLElement $node): ?string
    {
        $value = str_replace(',', '.', trim((string) ($node ?? '')));

        return is_numeric($value) ? (string) ((float) $value) : null;
    }

    /**
     * Inner markup of an XML node: escaped HTML (text) and inline XHTML (elements) alike.
     */
    protected function markupOf(SimpleXMLElement $node): string
    {
        $element = dom_import_simplexml($node);
        $markup = '';

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMText) {
                $markup .= $child->nodeValue;
            } elseif ($child instanceof DOMElement) {
                $markup .= $element->ownerDocument->saveXML($child);
            }
        }

        return $markup;
    }

    protected function first(SimpleXMLElement $node, string $xpath): ?SimpleXMLElement
    {
        $result = $node->xpath($xpath);

        return $result ? $result[0] : null;
    }

    /**
     * Read an attribute by its local name, whatever namespace prefix it has (bb:file, xml:base, ...).
     *
     * @param  list<string>  $names
     */
    protected function attributeByLocalName(DOMElement $element, array $names): string
    {
        foreach ($names as $name) {
            foreach ($element->attributes as $attribute) {
                if (strcasecmp($attribute->localName, $name) === 0 && trim($attribute->value) !== '') {
                    return trim($attribute->value);
                }
            }
        }

        return '';
    }

    protected function warn(string $message): void
    {
        $warning = 'Soal '.$this->questionNumber.': '.$message;

        if (! in_array($warning, $this->warnings, true) && count($this->warnings) < 100) {
            $this->warnings[] = $warning;
        }
    }

    /**
     * Decode XML bytes to UTF-8 (UTF-16 exports and Windows-1252 files without declaration).
     */
    protected function decodeXmlBytes(string $xml): string
    {
        if (str_starts_with($xml, "\xFF\xFE") || str_starts_with($xml, "\xFE\xFF")) {
            $xml = (string) mb_convert_encoding($xml, 'UTF-8', 'UTF-16');
            $xml = (string) preg_replace('/(<\?xml[^>]+encoding=["\'])UTF-16[A-Z]*(["\'])/i', '$1UTF-8$2', $xml);
        } elseif (preg_match('/<\?xml[^>]+encoding=["\']([^"\']+)["\']/i', substr($xml, 0, 500), $matches) && strtoupper(trim($matches[1])) !== 'UTF-8') {
            $converted = @mb_convert_encoding($xml, 'UTF-8', strtoupper(trim($matches[1])));
            if (is_string($converted) && $converted !== '') {
                $xml = (string) preg_replace('/(<\?xml[^>]+encoding=["\'])[^"\']+(["\'])/i', '$1UTF-8$2', $converted);
            }
        }

        $xml = (string) preg_replace('/^\xEF\xBB\xBF/', '', $xml);

        if (! mb_check_encoding($xml, 'UTF-8')) {
            $xml = (string) mb_convert_encoding($xml, 'UTF-8', 'Windows-1252');
        }

        return $xml;
    }

    /**
     * Parse question XML leniently (namespaces removed so XPath works on every export).
     */
    protected function loadXml(string $xml): ?SimpleXMLElement
    {
        $xml = $this->decodeXmlBytes($xml);

        // Remove non-printable control characters that break XML (keep tab, newline, carriage return)
        $xml = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $xml);

        // Convert named HTML entities (&nbsp;, &deg;, ...) that are not defined in XML
        $xml = (string) preg_replace_callback('/&([a-zA-Z][a-zA-Z0-9]*);/', function (array $matches): string {
            if (in_array(strtolower($matches[1]), ['amp', 'lt', 'gt', 'quot', 'apos'], true)) {
                return $matches[0];
            }

            $decoded = html_entity_decode($matches[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return $decoded === $matches[0] ? ' ' : htmlspecialchars($decoded, ENT_QUOTES | ENT_XML1, 'UTF-8');
        }, $xml);

        // Escape bare ampersands
        $xml = (string) preg_replace('/&(?!(?:amp|lt|gt|quot|apos|#\d+|#x[a-f\d]+);)/i', '&amp;', $xml);

        // Drop namespaces: xmlns declarations and element prefixes (<bb:item> => <item>)
        $xml = (string) preg_replace('/\sxmlns(:\w+)?=(["\']).*?\2/s', '', $xml);
        $xml = (string) preg_replace_callback('/<(\/?)[a-zA-Z0-9_\-]+:([a-zA-Z0-9_\-]+)/', fn (array $matches) => '<'.$matches[1].$matches[2], $xml);
        $xml = (string) preg_replace_callback('/<[a-zA-Z][^<>]*>/', fn (array $matches) => (string) preg_replace('/\s(?!xml:)[a-zA-Z0-9_\-]+:([a-zA-Z0-9_\-]+=)/', ' $1', $matches[0]), $xml);

        $options = LIBXML_NOCDATA | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET | (defined('LIBXML_PARSEHUGE') ? LIBXML_PARSEHUGE : 0);

        $previous = libxml_use_internal_errors(true);
        $root = simplexml_load_string($xml, SimpleXMLElement::class, $options);

        if ($root === false) {
            $document = new DOMDocument;
            $document->recover = true;
            $root = @$document->loadXML($xml, $options | LIBXML_COMPACT) ? simplexml_import_dom($document) : null;
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $root ?: null;
    }

    /**
     * Find all .docx files inside the ZIP.
     *
     * @return list<string>
     */
    protected function findDocxFiles(): array
    {
        $docxFiles = [];
        foreach ($this->entries as $path => $entry) {
            if (str_ends_with(strtolower($path), '.docx') && ! str_starts_with(basename($path), '~$')) {
                $docxFiles[] = $entry;
            }
        }

        return $docxFiles;
    }

    /**
     * Parse questions from a Word (.docx) file found inside the ZIP archive.
     *
     * @param  list<string>  $docxEntries
     * @return list<array<string, mixed>>
     */
    protected function parseDocxFromZip(array $docxEntries): array
    {
        $allQuestions = [];

        foreach ($docxEntries as $entryName) {
            $docxBytes = $this->zip->getFromName($entryName);
            if (! $docxBytes) {
                continue;
            }

            $tempPath = tempnam(sys_get_temp_dir(), 'ev_docx_').'.docx';
            file_put_contents($tempPath, $docxBytes);

            try {
                $questions = $this->docxImportService->parseDocx($tempPath);
                $allQuestions = array_merge($allQuestions, $questions);
            } finally {
                @unlink($tempPath);
            }
        }

        if (empty($allQuestions)) {
            throw new \RuntimeException('Ditemukan berkas Word (.docx) di dalam ZIP, tetapi tidak dapat mengurai butir soal dari dokumen tersebut.');
        }

        return $allQuestions;
    }
}
