<?php

namespace App\Support;

/**
 * Going from section to section, like in Google Forms. After a section the respondent goes on to
 * the next section, to another section, or straight to the end ("Kirim formulir"), as the teacher
 * set for that section. A multiple-choice or drop-down question set to "Buka bagian berdasarkan
 * jawaban" decides by its answer instead (the last such question answered in the section).
 *
 * Only the sections on a respondent's way count: questions in sections they skipped are not
 * answered, required, scored or graded for them.
 *
 * A section is known by its key: the id of its section item, or 0 for the first section of a
 * form that does not start with a section item (the form's own title is its header). Where a
 * section leads (its "goTo"): "next", "submit" or the key of another section. The first
 * section's is kept in the settings (firstSectionGoTo), the others on their section items.
 */
class SectionFlow
{
    public const NEXT = 'next';

    public const SUBMIT = 'submit';

    /** The key of a first section without a section item. */
    public const FIRST = 0;

    /** Question types that can send the respondent to a section by their answer. */
    public const BRANCHING_TYPES = ['Multiple choice', 'Drop-down', 'Dropdown'];

    /**
     * The sections in their order, each with its key, its section item (none for a first section
     * without one) and the items in it.
     *
     * @param  array<int, mixed>  $items
     * @return list<array{key: int, header: array<string, mixed>|null, items: list<array<string, mixed>>}>
     */
    public static function sections(array $items): array
    {
        $sections = [];

        foreach (array_values(array_filter($items, 'is_array')) as $index => $item) {
            if (($item['type'] ?? '') === QuizItems::SECTION) {
                $sections[] = ['key' => (int) ($item['id'] ?? 0), 'header' => $item, 'items' => []];

                continue;
            }

            if ($index === 0) {
                $sections[] = ['key' => self::FIRST, 'header' => null, 'items' => []];
            }

            $sections[array_key_last($sections)]['items'][] = $item;
        }

        return $sections;
    }

    /**
     * Whether anyone can be sent somewhere other than the next section, and so skip questions.
     *
     * @param  array<int, mixed>  $items
     * @param  array<string, mixed>  $settings
     */
    public static function branches(array $items, array $settings): bool
    {
        $sections = self::sections($items);

        if (count($sections) < 2) {
            return false;
        }

        foreach ($sections as $section) {
            if (self::goToOf($section, $settings) !== self::NEXT) {
                return true;
            }

            foreach ($section['items'] as $item) {
                if (self::isBranching($item) && collect($item['optionGoTo'] ?? [])->contains(fn (mixed $target): bool => $target !== self::NEXT)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The keys of the sections on the respondent's way, in order, given their answers. The way
     * ends at "submit", after the last section, or where it would come back to a section already
     * on it.
     *
     * @param  array<int, mixed>  $items
     * @param  array<string, mixed>  $settings
     * @param  array<int|string, mixed>  $answers
     * @return list<int>
     */
    public static function path(array $items, array $settings, array $answers): array
    {
        $sections = self::sections($items);
        $positions = array_flip(array_column($sections, 'key'));
        $path = [];
        $at = $sections === [] ? null : 0;

        while ($at !== null && ! in_array($sections[$at]['key'], $path, true)) {
            $path[] = $sections[$at]['key'];
            $target = self::targetAfter($sections[$at], $settings, $answers);

            $at = match (true) {
                $target === self::SUBMIT => null,
                $target === self::NEXT => isset($sections[$at + 1]) ? $at + 1 : null,
                default => $positions[$target] ?? (isset($sections[$at + 1]) ? $at + 1 : null),
            };
        }

        return $path;
    }

    /**
     * The ids of the questions on the respondent's way, or null when the form sends everyone
     * through every section (all questions count).
     *
     * @param  array<int, mixed>  $items
     * @param  array<string, mixed>  $settings
     * @param  array<int|string, mixed>  $answers
     * @return list<string>|null
     */
    public static function questionIdsOnPath(array $items, array $settings, array $answers): ?array
    {
        if (! self::branches($items, $settings)) {
            return null;
        }

        $path = self::path($items, $settings, $answers);
        $ids = [];

        foreach (self::sections($items) as $section) {
            if (in_array($section['key'], $path, true)) {
                foreach (QuizItems::questions($section['items']) as $question) {
                    $ids[] = (string) $question['id'];
                }
            }
        }

        return $ids;
    }

    /**
     * What a response keeps of a form that branches: only the answers on the respondent's way,
     * and the questions they were given narrowed to that way. A form that does not branch keeps
     * everything.
     *
     * @param  array<int, mixed>  $items
     * @param  array<string, mixed>  $settings
     * @param  array<int|string, mixed>  $answers
     * @param  list<int|string>|null  $assignedIds
     * @return array{answers: array<int|string, mixed>, questionIds: list<int|string>|null}
     */
    public static function keepOnPath(array $items, array $settings, array $answers, ?array $assignedIds): array
    {
        $onPath = self::questionIdsOnPath($items, $settings, $answers);

        if ($onPath === null) {
            return ['answers' => $answers, 'questionIds' => $assignedIds];
        }

        $kept = array_filter($answers, fn (int|string $id): bool => in_array((string) $id, $onPath, true), ARRAY_FILTER_USE_KEY);
        $given = $assignedIds === null
            ? $onPath
            : array_values(array_filter($assignedIds, fn (int|string $id): bool => in_array((string) $id, $onPath, true)));

        return ['answers' => $kept, 'questionIds' => $given];
    }

    /**
     * The flow as saved: targets that point to a section that is gone (or to the section itself)
     * become "next", and only choice questions keep a target for each of their options.
     *
     * @param  array<int, mixed>  $items
     * @param  array<string, mixed>  $settings
     * @return array{items: array<int, mixed>, settings: array<string, mixed>}
     */
    public static function normalize(array $items, array $settings): array
    {
        $sections = self::sections($items);
        $keys = array_column($sections, 'key');
        $clean = function (mixed $target, int $own) use ($keys): string|int {
            if ($target === self::SUBMIT) {
                return self::SUBMIT;
            }

            return is_numeric($target) && (int) $target !== $own && in_array((int) $target, $keys, true) ? (int) $target : self::NEXT;
        };

        $sectionOf = [];
        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                $sectionOf[(string) ($item['id'] ?? '')] = $section['key'];
            }
        }

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['type'] ?? '') === QuizItems::SECTION) {
                $items[$index]['goTo'] = $clean($item['goTo'] ?? self::NEXT, (int) ($item['id'] ?? 0));

                continue;
            }

            unset($items[$index]['goTo']);

            if (! in_array($item['type'] ?? '', self::BRANCHING_TYPES, true) || count($sections) < 2) {
                unset($items[$index]['branching'], $items[$index]['optionGoTo']);

                continue;
            }

            $own = $sectionOf[(string) ($item['id'] ?? '')] ?? self::FIRST;
            $targets = array_values(is_array($item['optionGoTo'] ?? null) ? $item['optionGoTo'] : []);
            $items[$index]['branching'] = (bool) ($item['branching'] ?? false);
            $items[$index]['optionGoTo'] = array_map(
                fn (int $at): string|int => $clean($targets[$at] ?? self::NEXT, $own),
                array_keys(array_values(is_array($item['options'] ?? null) ? $item['options'] : [])),
            );
        }

        if (($sections[0]['key'] ?? null) === self::FIRST && count($sections) > 1) {
            $settings['firstSectionGoTo'] = $clean($settings['firstSectionGoTo'] ?? self::NEXT, self::FIRST);
        } else {
            unset($settings['firstSectionGoTo']);
        }

        return ['items' => $items, 'settings' => $settings];
    }

    /**
     * Where a section leads by its own setting.
     *
     * @param  array{key: int, header: array<string, mixed>|null, items: list<array<string, mixed>>}  $section
     * @param  array<string, mixed>  $settings
     */
    protected static function goToOf(array $section, array $settings): string|int
    {
        $target = $section['header'] === null ? ($settings['firstSectionGoTo'] ?? self::NEXT) : ($section['header']['goTo'] ?? self::NEXT);

        return in_array($target, [self::NEXT, self::SUBMIT], true) ? $target : (is_numeric($target) ? (int) $target : self::NEXT);
    }

    /**
     * Where the respondent goes after a section: the answer to its last answered question that
     * sends by answer, otherwise the section's own setting.
     *
     * @param  array{key: int, header: array<string, mixed>|null, items: list<array<string, mixed>>}  $section
     * @param  array<string, mixed>  $settings
     * @param  array<int|string, mixed>  $answers
     */
    protected static function targetAfter(array $section, array $settings, array $answers): string|int
    {
        $target = self::goToOf($section, $settings);

        foreach ($section['items'] as $item) {
            $answer = $answers[$item['id'] ?? ''] ?? null;

            if (! self::isBranching($item) || ! is_string($answer)) {
                continue;
            }

            $at = array_search($answer, array_values($item['options'] ?? []), true);

            if ($at !== false) {
                $chosen = $item['optionGoTo'][$at] ?? self::NEXT;
                $target = in_array($chosen, [self::NEXT, self::SUBMIT], true) ? $chosen : (is_numeric($chosen) ? (int) $chosen : self::NEXT);
            }
        }

        return $target;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected static function isBranching(array $item): bool
    {
        return ($item['branching'] ?? false) === true
            && in_array($item['type'] ?? '', self::BRANCHING_TYPES, true)
            && is_array($item['options'] ?? null);
    }
}
