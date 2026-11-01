<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * At most so many characters of text in a field of the editor, not counting its bold, italic,
 * underline and highlighter tags (see resources/js/utils/rich-editor.ts).
 */
class MaxVisibleCharacters implements ValidationRule
{
    public function __construct(public int $max) {}

    /**
     * The text as students read it: the stored text without its formatting tags.
     */
    public static function visibleText(string $value): string
    {
        return (string) preg_replace('/<\/?(?:b|i|u)>|<mark data-color="[a-z]+">|<\/mark>/', '', $value);
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && mb_strlen(self::visibleText($value)) > $this->max) {
            $fail("Teks paling banyak {$this->max} karakter.");
        }
    }
}
