<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Intrinsic;

use SqlSemantics\Model\Scalar\Temporal\PostgreSqlField;

/**
 * Resolves PostgreSQL's datetime unit aliases to one semantic field.
 * @visibility SqlSemantics
 */
final class ExtractionField
{
    /**
     * Normalizes server datetime-unit spellings, including the ten-byte lookup key.
     */
    public static function postgres(string $name): ?PostgreSqlField
    {
        $name = strtoupper($name);
        $canonical = match (substr($name, 0, 10)) {
            'C', 'CENT', 'CENTURIES' => 'CENTURY',
            'D', 'DAYS' => 'DAY',
            'DEC', 'DECADES', 'DECS' => 'DECADE',
            'H', 'HOURS', 'HR', 'HRS' => 'HOUR',
            'J', 'JD' => 'JULIAN',
            'M', 'MIN', 'MINS', 'MINUTES' => 'MINUTE',
            'MICROSECON', 'US', 'USEC', 'USECS', 'USECOND', 'USECONDS' => 'MICROSECONDS',
            'MIL', 'MILLENNIA', 'MILS' => 'MILLENNIUM',
            'MILLISECON', 'MS', 'MSEC', 'MSECS', 'MSECOND', 'MSECONDS' => 'MILLISECONDS',
            'MON', 'MONS', 'MONTHS' => 'MONTH',
            'QTR' => 'QUARTER',
            'S', 'SEC', 'SECS', 'SECONDS' => 'SECOND',
            'TIMEZONE_H' => 'TIMEZONE_HOUR',
            'TIMEZONE_M' => 'TIMEZONE_MINUTE',
            'W', 'WEEKS' => 'WEEK',
            'Y', 'YR', 'YRS', 'YEARS' => 'YEAR',
            default => $name,
        };
        return PostgreSqlField::tryFrom($canonical);
    }
}
