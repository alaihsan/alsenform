<?php

namespace App\Services;

use App\Support\DisplayTime;
use App\Support\DocxWriter;
use App\Support\MediaUrl;
use App\Support\QuizItems;
use App\Support\RichText;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ekspor Soal ke Word add-on: questions as a Word file written like the import template
 * (DocxImportTemplate), so the file goes back in through the Word import (DocxImportService) as
 * the same questions: text and formatting, pictures, options, tables, points, required, answer
 * key, description ("Keterangan:") and explanation ("Pembahasan:"). What the template has no line
 * for (sections, videos, file upload questions) is written as a note ("//"), which the import skips.
 */
class DocxQuestionExport
{
    protected const META_COLOR = '3730A3';

    protected const NOTE_COLOR = '64748B';

    /** A font for Arabic that comes with Windows and Word for Mac. */
    protected const ARABIC_FONT = 'Traditional Arabic';

    protected const EMU_PER_TWIP = 635;

    protected const EMU_PER_PIXEL = 9525;

    /** The tallest picture, 22 cm. */
    protected const MAX_PICTURE_HEIGHT = 7920000;

    /**
     * The words written after "Tipe:" for each question type (among DocxImportService::TYPE_ALIASES).
     *
     * @var array<string, string>
     */
    public const TYPE_NAMES = [
        'Multiple choice' => 'Pilihan Ganda',
        'Checkboxes' => 'Kotak Centang',
        'Drop-down' => 'Drop-down',
        'Dropdown' => 'Drop-down',
        'Short answer' => 'Isian Singkat',
        'Paragraph' => 'Uraian',
        'Multiple-choice grid' => 'Kisi Pilihan Ganda',
        'Tick box grid' => 'Kisi Kotak Centang',
        'Linear scale' => 'Skala Linear',
        'Rating' => 'Rating',
        'Date' => 'Tanggal',
        'Time' => 'Waktu',
    ];

    public function __construct(
        protected MediaUrl $mediaUrl = new MediaUrl,
    ) {}

    public function filename(string $title): string
    {
        $name = Str::slug(Str::limit(RichText::plain($title), 80, ''), '_');

        return 'soal_'.($name !== '' ? $name : 'falcon_forms').'.docx';
    }

    /**
     * Write the items of a form, or questions from the bank, to a Word file.
     *
     * @param  array<int, mixed>  $items
     * @return int The number of questions written.
     */
    public function save(string $path, array $items, string $title, string $description = '', string $source = ''): int
    {
        $doc = new DocxWriter;
        // Questions from the bank have no id, so they are told from sections by their type.
        $questions = array_filter($items, fn (mixed $item): bool => is_array($item) && ! QuizItems::isContent($item) && $this->isExportable($item));
        $points = array_sum(array_map(fn (array $question): int => $this->points($question), $questions));

        $this->writeHeader($doc, $title, $description, array_filter([
            $source,
            count($questions).' soal',
            "{$points} poin",
            'diekspor '.DisplayTime::format(DisplayTime::now(), 'j M Y, H.i'),
        ]));

        $number = 0;
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (QuizItems::isContent($item)) {
                $this->writeContentItem($doc, $item);
            } elseif (! $this->isExportable($item)) {
                $type = (string) ($item['type'] ?? '');
                $typeName = match ($type) {
                    'File upload' => 'Unggah Berkas',
                    'Voice answer' => 'Jawaban Suara',
                    default => $type,
                };
                $this->note($doc, 'Soal "'.Str::limit(RichText::plain((string) ($item['title'] ?? '')), 80).'" tidak diekspor: tipe '.$typeName.' tidak ada di templat Import Soal.');
            } else {
                $this->writeQuestion($doc, $item, ++$number);
            }
        }

        $doc->save($path, RichText::plain($title));

        return $number;
    }

    /**
     * What the import leaves out: everything above the "MULAI SOAL" line.
     *
     * @param  list<string>  $facts
     */
    protected function writeHeader(DocxWriter $doc, string $title, string $description, array $facts): void
    {
        $title = RichText::trim($title);
        $doc->paragraph($this->runs($title !== '' ? $title : 'Soal Falcon Forms', ['bold' => true, 'size' => 16]), ['after' => 60, 'bidi' => RichText::isPredominantlyArabic($title)]);

        $description = RichText::trim($description);
        if (RichText::plain($description) !== '') {
            $doc->paragraph($this->runs($description, ['color' => '475569']), ['after' => 60, 'bidi' => RichText::isPredominantlyArabic($description)]);
        }

        $doc->paragraph([['text' => implode(' · ', $facts), 'color' => self::NOTE_COLOR, 'size' => 9]], ['after' => 40]);
        $doc->paragraph([[
            'text' => 'Berkas ini memakai format templat Import Soal Falcon Forms, jadi dapat diimpor kembali lewat Import Soal → Word (.docx). Yang diimpor hanya isi di bawah baris MULAI SOAL; baris yang diawali // adalah catatan.',
            'color' => self::NOTE_COLOR,
            'size' => 9,
            'italic' => true,
        ]], ['after' => 120]);
        $doc->paragraph([['text' => 'MULAI SOAL', 'bold' => true, 'color' => self::META_COLOR]], ['align' => 'center', 'fill' => 'EEF2FF', 'before' => 60, 'after' => 240]);
    }

    /**
     * @param  array<string, mixed>  $question
     */
    protected function writeQuestion(DocxWriter $doc, array $question, int $number): void
    {
        $type = (string) $question['type'];
        $title = RichText::trim((string) ($question['title'] ?? ''));
        $title = RichText::plain($title) !== '' ? $title : QuizItems::UNTITLED_QUESTION;
        $options = $this->texts($question['options'] ?? []);
        $line = ['after' => 40, 'keepNext' => true];

        // The number in bold, unless the whole title is: bold over a whole numbered line is taken for the template's style.
        $doc->paragraph(
            [['text' => "{$number}. ", 'bold' => ! RichText::isWhollyBold($title)], ...$this->runs($title)],
            ['before' => $number === 1 ? 0 : 240, 'bidi' => RichText::isPredominantlyArabic($title)] + $line,
        );

        foreach (is_array($question['media'] ?? null) ? $question['media'] : [] as $media) {
            $this->writeMedia($doc, $media);
        }

        $description = RichText::trim((string) ($question['description'] ?? ''));
        if (RichText::plain($description) !== '') {
            $doc->paragraph([$this->meta('Keterangan: '), ...$this->runs($description)], ['bidi' => RichText::isPredominantlyArabic($description)] + $line);
        }

        $trueFalseRows = $this->trueFalseRows($question);
        $matching = $trueFalseRows === null ? $this->matchingPairs($question) : null;
        $isTrueFalse = $type === 'Multiple choice' && $options === ['Benar', 'Salah'];

        $typeName = match (true) {
            $isTrueFalse, $trueFalseRows !== null => 'Benar Salah',
            $matching !== null => 'Menjodohkan',
            default => self::TYPE_NAMES[$type],
        };
        $doc->paragraph([$this->meta("Tipe: {$typeName}")], $line);
        $doc->paragraph([$this->meta('Poin: '.$this->points($question))], $line);
        if (! empty($question['required'])) {
            $doc->paragraph([$this->meta('Wajib: Ya')], $line);
        }
        if (in_array($type, ['Linear scale', 'Rating'], true)) {
            $doc->paragraph([$this->meta('Skala: '.$this->scaleOf($options))], $line);
        }

        $answer = $question['answer'] ?? null;
        $rows = $this->texts($question['rows'] ?? []);
        $columns = $this->texts($question['columns'] ?? []);

        $key = match (true) {
            $isTrueFalse => match ($this->optionIndex($answer, $options)) {
                0 => 'Benar',
                1 => 'Salah',
                default => '-',
            },
            in_array($type, ['Multiple choice', 'Drop-down', 'Dropdown', 'Checkboxes'], true) => $this->writeChoices($doc, $type, $options, $answer),
            $trueFalseRows !== null => $this->writeTrueFalseTable($doc, $trueFalseRows, $answer),
            $matching !== null => $this->writeMatchingTable($doc, $matching, $answer),
            in_array($type, ['Multiple-choice grid', 'Tick box grid'], true) => $this->writeGridTable($doc, $type, $rows, $columns, $answer),
            $type === 'Date' => is_string($answer) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $answer, $date) ? "{$date[3]}-{$date[2]}-{$date[1]}" : $this->textKey($answer),
            default => $this->textKey($answer),
        };

        $explanation = RichText::trim((string) ($question['explanation'] ?? ''));
        $hasExplanation = RichText::plain($explanation) !== '';
        $doc->paragraph([['text' => "Jawaban: {$key}", 'bold' => true]], ['after' => 40, 'keepNext' => $hasExplanation]);

        // After the answer line, as teachers write it; the import gives it to the question it ends.
        if ($hasExplanation) {
            $doc->paragraph([$this->meta('Pembahasan: '), ...$this->runs($explanation)], ['after' => 40, 'bidi' => RichText::isPredominantlyArabic($explanation)]);
        }
    }

    /**
     * The options as "A. ...", "B. ...", and their key: a letter, or the letters for checkboxes.
     *
     * @param  list<string>  $options
     */
    protected function writeChoices(DocxWriter $doc, string $type, array $options, mixed $answer): string
    {
        foreach ($options as $index => $option) {
            $doc->paragraph(
                [['text' => $this->letter($index).'. '], ...$this->runs(RichText::trim($option))],
                ['after' => 40, 'keepNext' => true, 'indent' => 360, 'bidi' => RichText::isPredominantlyArabic($option)],
            );
        }

        if ($type !== 'Checkboxes') {
            $index = $this->optionIndex($answer, $options);

            // A key that matches no option is written as it is, for the teacher to see.
            return $index !== null ? $this->letter($index) : $this->textKey($answer);
        }

        $indices = array_values(array_unique(array_filter(
            array_map(fn (mixed $key): ?int => $this->optionIndex($key, $options), is_array($answer) ? $answer : []),
            fn (?int $index): bool => $index !== null,
        )));
        sort($indices);

        return $indices !== [] ? implode(', ', array_map($this->letter(...), $indices)) : '-';
    }

    /**
     * The template's true/false table: No | Pernyataan | Benar/Salah, the key as "B, S, B".
     *
     * @param  list<array{0: string, 1: string}>  $rows  The number and the statement of each row.
     */
    protected function writeTrueFalseTable(DocxWriter $doc, array $rows, mixed $answer): string
    {
        $table = [['No', 'Pernyataan', 'Benar/Salah']];
        $tokens = [];
        foreach ($rows as $index => [$number, $statement]) {
            $table[] = [$number, $this->runs($statement), ''];
            $tokens[] = match ($this->gridColumns($answer, $index, ['Benar', 'Salah'])[0] ?? null) {
                0 => 'B',
                1 => 'S',
                default => '-',
            };
        }
        $this->table($doc, $table, [700, 6938, 2000]);

        return array_diff($tokens, ['-']) === [] ? '-' : implode(', ', $tokens);
    }

    /**
     * The template's matching table: No | Pernyataan | Pasangan, the key as "1C, 2A".
     *
     * @param  list<array{0: string, 1: string, 2: string}>  $pairs
     */
    protected function writeMatchingTable(DocxWriter $doc, array $pairs, mixed $answer): string
    {
        $columns = array_map(fn (array $pair, int $index): string => $this->letter($index).'. '.$pair[2], $pairs, array_keys($pairs));
        $table = [['No', 'Pernyataan', 'Pasangan']];
        $keys = [];
        foreach ($pairs as $index => [$number, $left, $right]) {
            $table[] = [$number, $this->runs($left), $this->runs($right)];
            $column = $this->gridColumns($answer, $index, $columns)[0] ?? null;
            if ($column !== null) {
                $keys[] = ($index + 1).$this->letter($column);
            }
        }
        $this->table($doc, $table, [700, 4469, 4469]);

        return $keys !== [] ? implode(', ', $keys) : '-';
    }

    /**
     * A grid with the correct cells marked X, the key "lihat tabel".
     *
     * @param  list<string>  $rows
     * @param  list<string>  $columns
     */
    protected function writeGridTable(DocxWriter $doc, string $type, array $rows, array $columns, mixed $answer): string
    {
        // A header in bold is taken for the style of the table: only when no column is bold text of its own.
        $boldHeader = array_filter($columns, fn (string $column): bool => str_contains($column, '<b>')) === [];
        $table = [[[['text' => 'Pernyataan', 'bold' => $boldHeader]], ...array_map(fn (string $column): array => $this->runs($column, $boldHeader ? ['bold' => true] : []), $columns)]];

        $hasMarks = false;
        foreach ($rows as $index => $row) {
            $marked = $this->gridColumns($answer, $index, $columns);
            if ($type === 'Multiple-choice grid') {
                $marked = array_slice($marked, 0, 1);
            }
            $hasMarks = $hasMarks || $marked !== [];
            $table[] = [$this->runs($row), ...array_map(fn (int $column): string => in_array($column, $marked, true) ? 'X' : '', array_keys($columns))];
        }

        $first = $columns === [] ? DocxWriter::CONTENT_WIDTH : 3038;
        $rest = $columns === [] ? [] : array_fill(0, count($columns), intdiv(DocxWriter::CONTENT_WIDTH - $first, count($columns)));
        $this->table($doc, $table, [$first, ...$rest], $boldHeader);

        return $hasMarks ? 'lihat tabel' : '-';
    }

    /**
     * @param  list<list<string|list<array<string, mixed>>>>  $rows
     * @param  list<int>  $widths
     */
    protected function table(DocxWriter $doc, array $rows, array $widths, bool $boldHeader = true): void
    {
        $doc->table($rows, ['header' => true, 'headerBold' => $boldHeader, 'headerFill' => 'E0E7FF', 'headerColor' => '312E81', 'widths' => $widths]);
    }

    /**
     * A grid of numbered statements with the columns Benar and Salah, which the template's true/false
     * table gives back: the number and the statement of each row, or null for another grid.
     *
     * @param  array<string, mixed>  $question
     * @return list<array{0: string, 1: string}>|null
     */
    protected function trueFalseRows(array $question): ?array
    {
        if ($question['type'] !== 'Multiple-choice grid' || $this->texts($question['columns'] ?? []) !== ['Benar', 'Salah']) {
            return null;
        }

        $rows = [];
        foreach ($this->texts($question['rows'] ?? []) as $row) {
            $parts = $this->numbered($row);
            if ($parts === null) {
                return null;
            }
            $rows[] = [$parts[1] === '.' ? $parts[0] : $parts[0].$parts[1], $parts[2]];
        }

        return $rows !== [] ? $rows : null;
    }

    /**
     * A grid of numbered statements and as many columns "A. ...", "B. ...", which the template's
     * matching table gives back: the number, the statement and its pair of each row, or null.
     *
     * @param  array<string, mixed>  $question
     * @return list<array{0: string, 1: string, 2: string}>|null
     */
    protected function matchingPairs(array $question): ?array
    {
        $rows = $this->texts($question['rows'] ?? []);
        $columns = $this->texts($question['columns'] ?? []);
        if ($question['type'] !== 'Multiple-choice grid' || $rows === [] || count($rows) !== count($columns)) {
            return null;
        }

        $pairs = [];
        foreach ($rows as $index => $row) {
            $parts = $this->numbered($row);
            $prefix = $this->letter($index).'. ';
            $right = RichText::dropPrefix($columns[$index], 3);
            // The import letters the columns "A. ", "B. " again.
            if ($parts === null || ! str_starts_with($columns[$index], $prefix) || RichText::plain($right) === '' || RichText::trim($right) !== $right) {
                return null;
            }
            $pairs[] = [$parts[1] === '.' ? $parts[0] : $parts[0].$parts[1], $parts[2], $right];
        }

        return $pairs;
    }

    /**
     * A row written "1. text" (the number not formatted): the number, its "." or ")" and the text.
     *
     * @return array{0: string, 1: string, 2: string}|null
     */
    protected function numbered(string $row): ?array
    {
        if (! preg_match('/^(\d+)([.)]) (?=\S)/', $row, $match)) {
            return null;
        }
        $text = RichText::dropPrefix($row, mb_strlen($match[0]));

        return RichText::trim($text) === $text ? [$match[1], $match[2], $text] : null;
    }

    /**
     * The columns of a grid row that the key marks, as indices.
     *
     * @param  list<string>  $columns
     * @return list<int>
     */
    protected function gridColumns(mixed $answer, int $row, array $columns): array
    {
        $value = is_array($answer) ? ($answer[$row] ?? null) : null;
        $indices = array_filter(
            array_map(fn (mixed $key): ?int => $this->optionIndex($key, $columns), is_array($value) ? $value : [$value]),
            fn (?int $index): bool => $index !== null,
        );
        $indices = array_values(array_unique($indices));
        sort($indices);

        return $indices;
    }

    /**
     * The option a key points at, read as scoring reads it (App\Support\QuizScoring): an index, or the text.
     *
     * @param  list<string>  $options
     */
    protected function optionIndex(mixed $key, array $options): ?int
    {
        if (! is_scalar($key) || $key === '' || is_bool($key)) {
            return null;
        }
        if (is_numeric($key) && isset($options[(int) $key])) {
            return (int) $key;
        }
        foreach ($options as $index => $option) {
            if ($option === (string) $key || RichText::plain($option) === (string) $key) {
                return $index;
            }
        }

        return null;
    }

    protected function textKey(mixed $answer): string
    {
        return is_scalar($answer) && ! is_bool($answer) && trim((string) $answer) !== '' ? trim((string) $answer) : '-';
    }

    /**
     * "1-5" from the options of a scale ("1" to "5").
     *
     * @param  list<string>  $options
     */
    protected function scaleOf(array $options): string
    {
        $numbers = array_values(array_filter($options, 'is_numeric'));

        return $numbers !== [] ? ((int) $numbers[0]).'-'.((int) end($numbers)) : '1-5';
    }

    /**
     * A picture uploaded to the school server goes in the file; a link or a video becomes a note.
     */
    protected function writeMedia(DocxWriter $doc, mixed $media): void
    {
        if (! is_array($media) || ! is_string($media['url'] ?? null) || $media['url'] === '') {
            return;
        }

        $picture = ($media['type'] ?? 'image') === 'image' ? $this->picture((string) $this->mediaUrl->normalize($media['url'])) : null;
        if ($picture === null) {
            $this->note($doc, match ($media['type'] ?? '') {
                'video' => 'Video: ',
                'audio' => 'Audio: ',
                default => 'Gambar: ',
            }.$media['url'], ['keepNext' => true]);

            return;
        }

        [$data, $extension, $pixelWidth, $pixelHeight] = $picture;
        $share = preg_match('/^(\d+(?:\.\d+)?)%$/', (string) ($media['width'] ?? ''), $match) ? min(1, max(0.1, (float) $match[1] / 100)) : 1.0;
        $width = (int) min(DocxWriter::CONTENT_WIDTH * self::EMU_PER_TWIP * $share, $pixelWidth * self::EMU_PER_PIXEL);
        $height = (int) round($width * $pixelHeight / max(1, $pixelWidth));
        if ($height > self::MAX_PICTURE_HEIGHT) {
            $width = (int) round($width * self::MAX_PICTURE_HEIGHT / $height);
            $height = self::MAX_PICTURE_HEIGHT;
        }

        $doc->image($data, $extension, $width, $height, [
            'align' => in_array($media['align'] ?? null, ['left', 'center', 'right'], true) ? $media['align'] : 'left',
            'after' => 60,
            'keepNext' => true,
        ]);
    }

    /**
     * A picture on the public disk as Word shows it: PNG, JPEG or GIF (a WebP upload is turned into
     * PNG), with its size in pixels. Null when it is not a picture of this server.
     *
     * @return array{0: string, 1: string, 2: int, 3: int}|null
     */
    protected function picture(string $url): ?array
    {
        if (! str_starts_with($url, '/storage/')) {
            return null;
        }

        $path = rawurldecode(Str::before(substr($url, strlen('/storage/')), '?'));
        if ($path === '' || str_contains($path, '..') || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $data = (string) Storage::disk('public')->get($path);
        $size = @getimagesizefromstring($data);
        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            return null;
        }

        $extension = match ($size[2]) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_JPEG => 'jpeg',
            IMAGETYPE_GIF => 'gif',
            default => null,
        };

        if ($extension === null) {
            $image = function_exists('imagecreatefromstring') ? @imagecreatefromstring($data) : false;
            if ($image === false) {
                return null;
            }
            imagesavealpha($image, true);
            ob_start();
            imagepng($image);
            $data = (string) ob_get_clean();
            $extension = 'png';
        }

        return [$data, $extension, $size[0], $size[1]];
    }

    /**
     * A section or a "title and description" item, as a note.
     *
     * @param  array<string, mixed>  $item
     */
    protected function writeContentItem(DocxWriter $doc, array $item): void
    {
        $title = RichText::trim((string) ($item['title'] ?? ''));
        $description = RichText::trim((string) ($item['description'] ?? ''));
        $label = $item['type'] === QuizItems::SECTION ? 'Bagian' : 'Judul';

        $doc->paragraph([
            ['text' => "// {$label}: ", 'bold' => true, 'color' => self::NOTE_COLOR],
            ...$this->runs(RichText::plain($title) !== '' ? $title : '-', ['bold' => true, 'color' => self::NOTE_COLOR]),
            ...(RichText::plain($description) !== '' ? [['text' => "\n"], ...$this->runs($description, ['color' => self::NOTE_COLOR])] : []),
        ], ['before' => 240, 'after' => 40, 'keepNext' => true]);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function note(DocxWriter $doc, string $text, array $options = []): void
    {
        $doc->paragraph([['text' => "// {$text}", 'color' => self::NOTE_COLOR, 'italic' => true, 'size' => 9]], ['after' => 40] + $options);
    }

    /**
     * @return array<string, mixed>
     */
    protected function meta(string $text): array
    {
        return ['text' => $text, 'color' => self::META_COLOR];
    }

    /**
     * Stored text as Word runs: its formatting, and Arabic right to left in an Arabic font.
     *
     * @param  array<string, mixed>  $base
     * @return list<array<string, mixed>>
     */
    protected function runs(string $text, array $base = []): array
    {
        $runs = [];
        foreach (RichText::runs($text) as $run) {
            foreach (RichText::scriptSegments($run['text']) as $segment) {
                $runs[] = [
                    ...$base,
                    'text' => $segment['text'],
                    'bold' => $run['b'] || ! empty($base['bold']),
                    'italic' => $run['i'] || ! empty($base['italic']),
                    'underline' => $run['u'],
                    'shade' => $run['mark'] !== null ? RichText::HIGHLIGHT_COLORS[$run['mark']] : null,
                    ...($segment['arabic'] ? ['rtl' => true, 'csFont' => self::ARABIC_FONT, 'csSize' => ($base['size'] ?? 11) + 5] : []),
                ];
            }
        }

        return $runs;
    }

    /**
     * @param  array<string, mixed>  $question
     */
    protected function isExportable(array $question): bool
    {
        return isset(self::TYPE_NAMES[$question['type'] ?? '']);
    }

    /**
     * @param  array<string, mixed>  $question
     */
    protected function points(array $question): int
    {
        return max(0, isset($question['points']) ? (int) $question['points'] : 1);
    }

    /**
     * @return list<string>
     */
    protected function texts(mixed $values): array
    {
        return array_values(array_map(fn (mixed $value): string => is_scalar($value) ? (string) $value : '', is_array($values) ? $values : []));
    }

    protected function letter(int $index): string
    {
        return chr(ord('A') + $index);
    }
}
