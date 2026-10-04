<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Convert the HTML of imported questions (ExamView / Blackboard) into the plain text
 * the quiz renderer understands, without losing meaning:
 *  - entities such as &lt; are decoded only after the markup is parsed, so "x < 7" survives;
 *  - paragraphs, line breaks, lists and table rows keep their line structure;
 *  - <sub>/<sup> become Unicode (H₂O, x²) or inline LaTeX \(^{...}\) rendered by KaTeX;
 *  - images are reported to a callback that returns the placeholder to show in the text.
 */
class HtmlToPlainText
{
    /**
     * Elements whose content starts on a new line.
     *
     * @var list<string>
     */
    protected const BLOCK_ELEMENTS = [
        'p', 'div', 'section', 'article', 'blockquote', 'pre', 'table', 'tbody', 'thead', 'tfoot',
        'ul', 'ol', 'dl', 'dt', 'dd', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'center', 'address', 'hr',
    ];

    /**
     * Elements that never contain question text.
     *
     * @var list<string>
     */
    protected const IGNORED_ELEMENTS = ['script', 'style', 'head', 'title', 'applet', 'object', 'embed', 'noscript', 'param', 'iframe'];

    /**
     * @var array<string, string>
     */
    protected const SUPERSCRIPTS = [
        '0' => '⁰', '1' => '¹', '2' => '²', '3' => '³', '4' => '⁴', '5' => '⁵', '6' => '⁶', '7' => '⁷', '8' => '⁸', '9' => '⁹',
        '+' => '⁺', '-' => '⁻', '−' => '⁻', '=' => '⁼', '(' => '⁽', ')' => '⁾', 'n' => 'ⁿ', 'i' => 'ⁱ',
    ];

    /**
     * @var array<string, string>
     */
    protected const SUBSCRIPTS = [
        '0' => '₀', '1' => '₁', '2' => '₂', '3' => '₃', '4' => '₄', '5' => '₅', '6' => '₆', '7' => '₇', '8' => '₈', '9' => '₉',
        '+' => '₊', '-' => '₋', '−' => '₋', '=' => '₌', '(' => '₍', ')' => '₎',
        'a' => 'ₐ', 'e' => 'ₑ', 'o' => 'ₒ', 'x' => 'ₓ', 'h' => 'ₕ', 'k' => 'ₖ', 'l' => 'ₗ', 'm' => 'ₘ', 'n' => 'ₙ', 'p' => 'ₚ', 's' => 'ₛ', 't' => 'ₜ',
    ];

    /**
     * Convert an HTML fragment to plain text.
     *
     * @param  (callable(string): string)|null  $onImage  Receives the image src and returns the text to put in its place.
     */
    public function convert(string $html, ?callable $onImage = null): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = null;
        foreach ($document->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $root = $child;
                break;
            }
        }

        if ($root === null) {
            return $this->tidy(strip_tags($html));
        }

        return $this->tidy($this->render($root, $onImage));
    }

    /**
     * Normalize plain (non-HTML) text.
     */
    public function plain(string $text): string
    {
        return $this->tidy(str_replace(["\r\n", "\r"], "\n", $text));
    }

    /**
     * Render the children of a node.
     *
     * @param  (callable(string): string)|null  $onImage
     */
    protected function render(DOMNode $node, ?callable $onImage): string
    {
        $output = '';

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $output .= preg_replace('/[ \t\r\n\f]+/', ' ', $child->nodeValue ?? '');

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::IGNORED_ELEMENTS, true)) {
                continue;
            }

            $output .= match (true) {
                $tag === 'br' => "\n",
                $tag === 'img' => $onImage ? (str_ends_with($output, ']') ? ' ' : '').$onImage(trim($child->getAttribute('src'))) : '',
                $tag === 'sup' => $this->script(trim($this->render($child, $onImage)), self::SUPERSCRIPTS, '^'),
                $tag === 'sub' => $this->script(trim($this->render($child, $onImage)), self::SUBSCRIPTS, '_'),
                $tag === 'li' => "\n- ".trim($this->render($child, $onImage))."\n",
                $tag === 'tr' => "\n".$this->renderRow($child, $onImage)."\n",
                in_array($tag, self::BLOCK_ELEMENTS, true) => "\n".$this->render($child, $onImage)."\n",
                default => $this->render($child, $onImage),
            };
        }

        return $output;
    }

    /**
     * Render a table row as "cell | cell | cell".
     *
     * @param  (callable(string): string)|null  $onImage
     */
    protected function renderRow(DOMElement $row, ?callable $onImage): string
    {
        $cells = [];
        foreach ($row->childNodes as $cell) {
            if ($cell instanceof DOMElement && in_array(strtolower($cell->tagName), ['td', 'th'], true)) {
                $cells[] = trim(preg_replace('/\s+/u', ' ', $this->render($cell, $onImage)));
            }
        }

        return implode(' | ', $cells);
    }

    /**
     * Write sub/superscript text with Unicode characters, or LaTeX when no Unicode form exists.
     *
     * @param  array<string, string>  $map
     */
    protected function script(string $text, array $map, string $latexOperator): string
    {
        if ($text === '') {
            return '';
        }

        $characters = mb_str_split($text);
        if (array_diff($characters, array_keys($map)) === []) {
            return implode('', array_map(fn (string $character) => $map[$character], $characters));
        }

        $escaped = strtr($text, ['\\' => '\\backslash ', '{' => '\\{', '}' => '\\}', '$' => '\\$', '%' => '\\%', '&' => '\\&', '#' => '\\#', '_' => '\\_', '^' => '\\^{}', '~' => '\\~{}']);

        return '\\('.$latexOperator.'{'.$escaped.'}\\)';
    }

    /**
     * Collapse whitespace while keeping meaningful line breaks.
     */
    protected function tidy(string $text): string
    {
        $text = str_replace(["\u{00A0}", "\u{200B}", "\u{FEFF}"], [' ', '', ''], $text);

        $lines = array_map(fn (string $line) => trim(preg_replace('/[ \t]+/u', ' ', $line)), explode("\n", $text));
        $text = implode("\n", $lines);
        $text = preg_replace("/\n{2,}/", "\n", $text);

        return trim($text);
    }
}
