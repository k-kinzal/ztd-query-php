<?php

declare(strict_types=1);

namespace MySqlMemory\Value;

use MySqlMemory\Typing\Domain;
use MySqlMemory\Typing\Ordering;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Compares and keys values of one domain, as the server sorts, groups and indexes them.
 *
 * NULL is lower than every value and equal to NULL. Strings follow their collation; numbers
 * their value, so `1.0` and `1.00` are equal.
 *
 * @visibility MySqlMemory
 */
final class Order
{
    /**
     * Compares two values of a domain: negative, zero or positive.
     */
    public static function compare(int|float|string|null $left, int|float|string|null $right, Domain $domain): int
    {
        if ($left === null || $right === null) {
            return ($left === null ? 0 : 1) - ($right === null ? 0 : 1);
        }

        return match ($domain->kind) {
            Kind::Integer, Kind::Year, Kind::Bit => Integer::compare((int) $left, $domain->unsigned, (int) $right, $domain->unsigned),
            Kind::Decimal => Decimal::compare((string) $left, (string) $right),
            Kind::Double => (float) $left <=> (float) $right,
            Kind::String, Kind::Json => Ordering::of($domain->collation)->compare((string) $left, (string) $right),
            Kind::Time => self::time((string) $left) <=> self::time((string) $right),
            Kind::Date, Kind::DateTime, Kind::Null => (string) $left <=> (string) $right,
        };
    }

    /**
     * Answers a key equal for two values of a domain exactly when they compare equal.
     */
    public static function key(int|float|string|null $value, Domain $domain): string
    {
        if ($value === null) {
            return "\0N";
        }

        return match ($domain->kind) {
            Kind::Integer, Kind::Year, Kind::Bit => 'i' . Integer::text((int) $value, $domain->unsigned),
            Kind::Decimal => 'd' . self::decimal((string) $value),
            Kind::Double => 'f' . ((float) $value === 0.0 ? '0' : Real::format((float) $value)),
            Kind::String, Kind::Json => 's' . Ordering::of($domain->collation)->key((string) $value),
            Kind::Date, Kind::Time, Kind::DateTime, Kind::Null => 't' . $value,
        };
    }

    /**
     * Writes a decimal without trailing zeros after the point.
     */
    public static function decimal(string $value): string
    {
        return str_contains($value, '.') ? Decimal::canonical(rtrim(rtrim($value, '0'), '.')) : $value;
    }

    /**
     * Answers the microseconds of a time text, for ordering.
     */
    public static function time(string $value): float
    {
        $negative = str_starts_with($value, '-');
        $parts = explode(':', ltrim($value, '-'));
        $micro = ((int) $parts[0] * 3600 + (int) ($parts[1] ?? 0) * 60) * 1000000 + (float) ($parts[2] ?? 0) * 1000000;

        return $negative ? -$micro : $micro;
    }
}
