<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Times as people at the institution read them: in the display timezone (APP_DISPLAY_TIMEZONE,
 * Asia/Jakarta by default) and in Indonesian ("19 Okt 2026", "5 menit yang lalu"), whatever the
 * application's or Carbon's locale happens to be. The one place every page and report formats
 * a time for people.
 */
class DisplayTime
{
    public const LOCALE = 'id';

    public static function timezone(): string
    {
        return (string) config('app.display_timezone', config('app.timezone'));
    }

    /**
     * The moment in the display timezone, speaking Indonesian.
     */
    public static function of(CarbonInterface $time): CarbonInterface
    {
        return $time->copy()->setTimezone(self::timezone())->locale(self::LOCALE);
    }

    public static function now(): CarbonInterface
    {
        return self::of(Carbon::now());
    }

    /**
     * A date (Y-m-d) taken as a day in the display timezone.
     */
    public static function day(string $date): CarbonInterface
    {
        return Carbon::parse($date, self::timezone())->locale(self::LOCALE);
    }

    /**
     * The time written with a date format, month and day names in Indonesian ("j M Y": 19 Okt 2026).
     */
    public static function format(?CarbonInterface $time, string $format): ?string
    {
        return $time === null ? null : self::of($time)->translatedFormat($format);
    }

    /**
     * How long ago, in Indonesian ("5 menit yang lalu").
     */
    public static function ago(?CarbonInterface $time): ?string
    {
        return $time?->copy()->locale(self::LOCALE)->diffForHumans();
    }
}
