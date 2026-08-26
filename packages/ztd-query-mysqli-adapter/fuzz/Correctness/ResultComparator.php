<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

use ZtdQuery\Connection\StatementInterface;

/**
 * @phpstan-import-type Row from StatementInterface
 */
final class ResultComparator
{
    /**
     * Compare two result sets.
     *
     * @param list<Row> $expected
     * @param list<Row> $actual
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
            $expected = $this->sortByKeys($expected, $primaryKeys);
            $actual = $this->sortByKeys($actual, $primaryKeys);
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
     * @param Row $expected
     * @param Row $actual
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
            return $this->compareFloat((float) $expected, (float) $actual);
        }

        if (str_contains($type, 'DECIMAL') || str_contains($type, 'NUMERIC')) {
            return $this->compareDecimal((string) $expected, (string) $actual);
        }

        if ($type === 'JSON') {
            return $this->compareJson((string) $expected, (string) $actual);
        }

        if (str_starts_with($type, 'SET')) {
            return $this->compareSet((string) $expected, (string) $actual);
        }

        return (string) $expected === (string) $actual;
    }

    /**
     * Answers whether two floats agree within what a driver rounds to.
     *
     * @param float $expected The expected
     * @param float $actual The actual
     *
     * @return bool What it answers
     */
    public function compareFloat(float $expected, float $actual): bool
    {
        if ($expected === 0.0) {
            return abs($actual) < 0.0001;
        }
        return abs($expected - $actual) / abs($expected) < 0.001;
    }

    /**
     * Answers whether two decimals agree once trailing zeros are dropped.
     *
     * @param string $expected The expected
     * @param string $actual The actual
     *
     * @return bool What it answers
     */
    public function compareDecimal(string $expected, string $actual): bool
    {
        $expected = rtrim(rtrim($expected, '0'), '.');
        $actual = rtrim(rtrim($actual, '0'), '.');
        return $expected === $actual;
    }

    /**
     * Answers whether two JSON texts say the same thing.
     *
     * @param string $expected The expected
     * @param string $actual The actual
     *
     * @return bool What it answers
     */
    public function compareJson(string $expected, string $actual): bool
    {
        $expectedDecoded = json_decode($expected, true);
        $actualDecoded = json_decode($actual, true);
        return $expectedDecoded === $actualDecoded;
    }

    /**
     * Answers whether two SET values name the same members.
     *
     * @param string $expected The expected
     * @param string $actual The actual
     *
     * @return bool What it answers
     */
    public function compareSet(string $expected, string $actual): bool
    {
        $expectedParts = explode(',', $expected);
        $actualParts = explode(',', $actual);
        sort($expectedParts);
        sort($actualParts);
        return $expectedParts === $actualParts;
    }

    /**
     * Answers the rows in the order the keys put them in.
     *
     * @param list<Row> $rows Rows to read
     * @param array<int, string> $keys The keys
     *
     * @return list<Row> What it answers
     */
    public function sortByKeys(array $rows, array $keys): array
    {
        usort($rows, function (array $a, array $b) use ($keys): int {
            foreach ($keys as $key) {
                $cmp = ($a[$key] ?? '') <=> ($b[$key] ?? '');
                if ($cmp !== 0) {
                    return $cmp;
                }
            }
            return 0;
        });
        return $rows;
    }
}
