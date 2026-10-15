<?php

namespace App\Support;

/**
 * The items of a form, like in Google Forms: questions, and items that are not questions, a
 * section (a page break with a title and a description) and a "title and description" (text
 * only). Items that are not questions are never answered, numbered, scored or drawn.
 */
class QuizItems
{
    public const SECTION = 'Section';

    public const TITLE_AND_DESCRIPTION = 'Title and description';

    public const CONTENT_TYPES = [self::SECTION, self::TITLE_AND_DESCRIPTION];

    public static function isContent(mixed $item): bool
    {
        return is_array($item) && in_array($item['type'] ?? '', self::CONTENT_TYPES, true);
    }

    public static function isQuestion(mixed $item): bool
    {
        return is_array($item) && isset($item['id']) && ! self::isContent($item);
    }

    /**
     * The questions among the items, in their order.
     *
     * @return list<array<string, mixed>>
     */
    public static function questions(mixed $items): array
    {
        return array_values(array_filter(is_array($items) ? $items : [], fn (mixed $item): bool => self::isQuestion($item)));
    }
}
