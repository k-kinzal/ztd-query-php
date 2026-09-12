<?php

declare(strict_types=1);

namespace Fuzz\Oracle;

use JsonException;

/**
 * Compares generated values with native PDO results without accepting unrelated coercions.
 */
final class StoredValueComparator
{
    /**
     * Compares round-tripped database values using their SQL storage semantics.
     *
     * @throws JsonException When either JSON value is malformed
     */
    public function compare(mixed $expected, mixed $actual, string $column): bool
    {
        if ($expected === null && $actual === null) {
            return true;
        }

        if ($expected === null || $actual === null) {
            return false;
        }

        if (is_bool($expected)) {
            return (bool) $actual === $expected;
        }

        if (is_float($expected)) {
            if (!is_numeric($actual)) {
                return false;
            }
            $actualFloat = (float) $actual;
            if ($expected === 0.0) {
                return abs($actualFloat) < 0.0001;
            }
            return abs($expected - $actualFloat) / abs($expected) < 0.001;
        }

        if (is_int($expected)) {
            if (str_starts_with($column, 'col_bit')) {
                if (is_int($actual)) {
                    return $expected === $actual;
                }
                $actualStr = is_string($actual) ? $actual : '';
                $actualInt = $actualStr === '' ? 0 : ord($actualStr);
                return $expected === $actualInt;
            }
            return is_numeric($actual) && $expected === (int) $actual;
        }

        if (is_string($expected)) {
            $actualStr = is_string($actual) ? $actual : (is_scalar($actual) ? (string) $actual : '');
            if ($column === 'col_json') {
                $expectedJson = json_decode($expected, true, 512, JSON_THROW_ON_ERROR);
                $actualJson = json_decode($actualStr, true, 512, JSON_THROW_ON_ERROR);
                return $expectedJson === $actualJson;
            }

            if ($column === 'col_set') {
                $expectedParts = explode(',', $expected);
                $actualParts = explode(',', $actualStr);
                sort($expectedParts);
                sort($actualParts);
                return $expectedParts === $actualParts;
            }

            return $expected === $actual;
        }

        return $expected === $actual;
    }
}
