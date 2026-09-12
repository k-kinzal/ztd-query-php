<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

/**
 * Compares SQL row values while accounting for database type representations.
 */
final class ResultComparator
{
    /**
     * Compare two result sets.
     *
     * @param array<int, array<string, mixed>> $expected
     * @param array<int, array<string, mixed>> $actual
     * @param array<int, string> $primaryKeys
     * @param array<string, string> $columnTypes Column name => MySQL type
     * @param bool $ordered Whether the results are expected to be in the same order
     */
    public function compareRows(
        array $expected,
        array $actual,
        array $primaryKeys = [],
        array $columnTypes = [],
        bool $ordered = false
    ): bool {
        if (count($expected) !== count($actual)) {
            return false;
        }

        if (!$ordered && $primaryKeys !== []) {
            $expected = (new RowOrdering())->sortByKeys($expected, $primaryKeys);
            $actual = (new RowOrdering())->sortByKeys($actual, $primaryKeys);
        }

        foreach ($expected as $i => $expectedRow) {
            if (!isset($actual[$i])) {
                return false;
            }
            if (!$this->compareRow($expectedRow, $actual[$i], $columnTypes)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compare two single rows.
     *
     * @param array<string, mixed> $expected
     * @param array<string, mixed> $actual
     * @param array<string, string> $columnTypes
     */
    public function compareRow(array $expected, array $actual, array $columnTypes = []): bool
    {
        if (array_keys($expected) !== array_keys($actual)) {
            return false;
        }

        foreach ($expected as $column => $expectedValue) {
            $actualValue = $actual[$column] ?? null;
            $type = strtoupper($columnTypes[$column] ?? '');

            if (!$this->compareValue($expectedValue, $actualValue, $type, $column)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compare two values with type-appropriate tolerance.
     */
    public function compareValue(mixed $expected, mixed $actual, string $type = '', string $column = ''): bool
    {
        if ($expected === null && $actual === null) {
            return true;
        }
        if ($expected === null || $actual === null) {
            return false;
        }

        assert(is_scalar($expected));
        assert(is_scalar($actual));

        if (str_contains($type, 'FLOAT') || str_contains($type, 'DOUBLE')) {
            return (new ScalarComparison())->compareFloat((float) $expected, (float) $actual);
        }

        if (str_contains($type, 'DECIMAL') || str_contains($type, 'NUMERIC')) {
            return (new ScalarComparison())->compareDecimal((string) $expected, (string) $actual);
        }

        if ($type === 'JSON') {
            return (new ScalarComparison())->compareJson((string) $expected, (string) $actual);
        }

        if (str_starts_with($type, 'SET')) {
            return (new ScalarComparison())->compareSet((string) $expected, (string) $actual);
        }

        return (string) $expected === (string) $actual;
    }










}
