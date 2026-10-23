<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Spatial;

use MySqlMemory\Error\Family\DataError;

/**
 * The axis-order option of spatial WKT and WKB conversions.
 *
 * Keys and values ignore case and surrounding whitespace. Duplicate keys and
 * malformed separators report their own SQL conditions before a value is interpreted.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/gis-format-conversion-functions.html.
 *
 * @visibility MySqlMemory
 */
final class AxisOptions
{
    /**
     * Answers latitude-first, longitude-first, or NULL for the reference system's order.
     */
    public static function parse(string $text, string $function): ?bool
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        self::separators($text, $function);
        $pairs = [];
        foreach (explode(',', $text) as $pair) {
            $parts = explode('=', $pair);
            if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
                throw DataError::InvalidOptionPair->error(trim($pair), $function);
            }
            [$key, $value] = array_map(static fn (string $part): string => strtolower(trim($part)), $parts);
            if (isset($pairs[$key])) {
                throw DataError::DuplicateOptionKey->error($key, $function);
            }
            $pairs[$key] = $value;
        }
        ksort($pairs);
        $result = null;
        foreach ($pairs as $key => $value) {
            if ($key !== 'axis-order') {
                throw DataError::InvalidOptionKey->error($key, $function);
            }
            $result = match ($value) {
                'lat-long' => true,
                'long-lat' => false,
                'srid-defined' => null,
                default => throw DataError::InvalidOptionValue->error($value, $key, $function),
            };
        }

        return $result;
    }

    /**
     * Checks separators without losing whether an empty option is leading, trailing or interior.
     */
    public static function separators(string $text, string $function): void
    {
        if (in_array($text[0], [',', '='], true)) {
            throw DataError::InvalidOptionStart->error($function, $text[0]);
        }
        if (in_array(substr($text, -1), [',', '='], true)) {
            throw DataError::InvalidOptionEnd->error($function, substr($text, -1));
        }
        if (preg_match('/([,=])\s*([,=])/', $text, $pair) === 1) {
            throw DataError::InvalidOptionSequence->error($function, $pair[1] . $pair[2]);
        }
    }
}
