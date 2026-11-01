<?php

namespace App\Support;

/**
 * Text as the editor stores it: plain text with bold, italic, underline and highlighter tags
 * (<b>, <i>, <u>, <mark data-color="...">, see resources/js/utils/rich-editor.ts). Any other
 * markup is text. Read here as runs of text with the same formatting, for the Word export and
 * import, and written back the way the editor writes it, so the same text gives the same string.
 *
 * A run is ['text' => string, 'b' => bool, 'i' => bool, 'u' => bool, 'mark' => colour|null].
 */
class RichText
{
    /**
     * The highlighter colours and their hex, as HIGHLIGHT_COLORS in resources/js/utils/rich-content.ts.
     *
     * @var array<string, string>
     */
    public const HIGHLIGHT_COLORS = [
        'yellow' => 'FDE047',
        'green' => '86EFAC',
        'blue' => '93C5FD',
        'pink' => 'F9A8D4',
        'orange' => 'FDBA74',
    ];

    /**
     * Arabic letters, Quranic marks and ligatures (ARABIC_REGEX in rich-content.ts).
     */
    public const ARABIC = '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u';

    /**
     * A phrase of Arabic words with the spaces and brackets between them (ARABIC_BLOCK_REGEX).
     */
    protected const ARABIC_PHRASE = '/([\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}\x{0660}-\x{0669} \t\(\)\[\]\{\}\x{FD3E}\x{FD3F}\x{06DD}\x{06DE}\x{06D6}-\x{06ED}]+)/u';

    protected const TAG = '/(<\/?(?:b|i|u)>|<mark data-color="(?:yellow|green|blue|pink|orange)">|<\/mark>)/';

    /**
     * What PHP's trim() takes off.
     */
    protected const TRIMMED = " \t\n\r\0\x0B";

    /**
     * @return list<array{text: string, b: bool, i: bool, u: bool, mark: string|null}>
     */
    public static function runs(string $text): array
    {
        $runs = [];
        $depth = ['b' => 0, 'i' => 0, 'u' => 0];
        $marks = [];

        foreach (preg_split(self::TAG, $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $index => $part) {
            if ($index % 2 === 1) {
                if (str_starts_with($part, '<mark')) {
                    $marks[] = (string) preg_replace('/^<mark data-color="([a-z]+)">$/', '$1', $part);
                } elseif ($part === '</mark>') {
                    array_pop($marks);
                } else {
                    // A closing tag without its opening one is ignored, as the editor's renderer does.
                    $name = trim($part, '</>');
                    $depth[$name] = max(0, $depth[$name] + (str_starts_with($part, '</') ? -1 : 1));
                }

                continue;
            }

            if ($part !== '') {
                $runs[] = ['text' => $part, 'b' => $depth['b'] > 0, 'i' => $depth['i'] > 0, 'u' => $depth['u'] > 0, 'mark' => $marks === [] ? null : end($marks)];
            }
        }

        return $runs;
    }

    /**
     * Runs as stored text, tags nested highlighter, bold, italic, underline like the editor's
     * serializeEditor(): a line break carries no formatting, so formatting runs on over it.
     *
     * @param  list<array{text: string, b?: bool, i?: bool, u?: bool, mark?: string|null}>  $runs
     */
    public static function serialize(array $runs): string
    {
        $output = '';
        $open = [];

        foreach ($runs as $run) {
            foreach (preg_split('/(\n)/', $run['text'], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $text) {
                $wanted = $text === "\n" ? $open : array_values(array_filter([
                    isset($run['mark'], self::HIGHLIGHT_COLORS[$run['mark']]) ? 'mark:'.$run['mark'] : null,
                    ! empty($run['b']) ? 'b' : null,
                    ! empty($run['i']) ? 'i' : null,
                    ! empty($run['u']) ? 'u' : null,
                ]));

                $shared = 0;
                while ($shared < count($open) && $shared < count($wanted) && $open[$shared] === $wanted[$shared]) {
                    $shared++;
                }

                $output .= implode('', array_map(self::closeTag(...), array_reverse(array_slice($open, $shared))))
                    .implode('', array_map(self::openTag(...), array_slice($wanted, $shared)))
                    .$text;
                $open = $wanted;
            }
        }

        return $output.implode('', array_map(self::closeTag(...), array_reverse($open)));
    }

    /**
     * The text written the way the editor writes it.
     */
    public static function normalize(string $text): string
    {
        return self::serialize(self::runs($text));
    }

    /**
     * The text as students read it, without its formatting.
     */
    public static function plain(string $text): string
    {
        return (string) preg_replace(self::TAG, '', $text);
    }

    /**
     * Without the spaces and line breaks around the text (what trim() takes off its plain text).
     */
    public static function trim(string $text): string
    {
        $runs = self::runs($text);

        while ($runs !== []) {
            $runs[0]['text'] = ltrim($runs[0]['text'], self::TRIMMED);
            if ($runs[0]['text'] !== '') {
                break;
            }
            array_shift($runs);
        }
        while ($runs !== []) {
            $last = count($runs) - 1;
            $runs[$last]['text'] = rtrim($runs[$last]['text'], self::TRIMMED);
            if ($runs[$last]['text'] !== '') {
                break;
            }
            array_pop($runs);
        }

        return self::serialize($runs);
    }

    /**
     * Without the first characters of its plain text, e.g. the "1. " of a numbered line.
     */
    public static function dropPrefix(string $text, int $characters): string
    {
        $runs = [];
        foreach (self::runs($text) as $run) {
            if ($characters > 0) {
                $length = mb_strlen($run['text']);
                $run['text'] = mb_substr($run['text'], min($characters, $length));
                $characters -= $length;
            }
            if ($run['text'] !== '') {
                $runs[] = $run;
            }
        }

        return self::serialize($runs);
    }

    /**
     * Whether every visible character is bold.
     */
    public static function isWhollyBold(string $text): bool
    {
        $visible = array_filter(self::runs($text), fn (array $run): bool => trim($run['text']) !== '');

        return $visible !== [] && array_filter($visible, fn (array $run): bool => ! $run['b']) === [];
    }

    /**
     * The text without bold, when all of it is bold.
     */
    public static function withoutWholeBold(string $text): string
    {
        if (! self::isWhollyBold($text)) {
            return $text;
        }

        return self::serialize(array_map(fn (array $run): array => ['b' => false] + $run, self::runs($text)));
    }

    public static function hasArabic(string $text): bool
    {
        return preg_match(self::ARABIC, $text) === 1;
    }

    /**
     * Whether more of the text is Arabic than Latin, so it is written right to left.
     */
    public static function isPredominantlyArabic(string $text): bool
    {
        $plain = self::plain($text);

        return preg_match_all(self::ARABIC, $plain) > preg_match_all('/[a-zA-Z]/', $plain);
    }

    /**
     * The text cut into Arabic phrases and other text, in order.
     *
     * @return list<array{text: string, arabic: bool}>
     */
    public static function scriptSegments(string $plain): array
    {
        $segments = [];
        foreach (preg_split(self::ARABIC_PHRASE, $plain, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $segment) {
            $isArabic = self::hasArabic($segment);
            // Spaces and brackets without Arabic around them stay with the text they are in.
            if (! $isArabic && $segments !== [] && ! $segments[count($segments) - 1]['arabic']) {
                $segments[count($segments) - 1]['text'] .= $segment;
            } else {
                $segments[] = ['text' => $segment, 'arabic' => $isArabic];
            }
        }

        return $segments;
    }

    protected static function openTag(string $key): string
    {
        return str_starts_with($key, 'mark:') ? '<mark data-color="'.substr($key, 5).'">' : "<{$key}>";
    }

    protected static function closeTag(string $key): string
    {
        return str_starts_with($key, 'mark:') ? '</mark>' : "</{$key}>";
    }
}
