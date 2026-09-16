<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

use DateTime;
use Faker\Provider\DateTime as NativeDateTimeProvider;

/**
 * Keeps fixture dates reproducible when a saved input is replayed on another day.
 */
final class FixedDateTimeProvider
{
    /**
     * Format a seeded date using a fixed upper bound.
     */
    public function date(string $format = 'Y-m-d'): string
    {
        return NativeDateTimeProvider::dateTime(1577836800, 'UTC')->format($format);
    }

    /**
     * Format a seeded time using a fixed upper bound.
     */
    public function time(string $format = 'H:i:s'): string
    {
        return NativeDateTimeProvider::dateTime(1577836800, 'UTC')->format($format);
    }

    /**
     * Generate a seeded timestamp independently of the wall clock.
     */
    public function dateTime(): DateTime
    {
        return NativeDateTimeProvider::dateTime(1577836800, 'UTC');
    }
}
