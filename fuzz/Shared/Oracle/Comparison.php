<?php

declare(strict_types=1);

namespace Fuzz\Shared\Oracle;

/**
 * Compares observable values without using the rewriter to compute expectations.
 */
final class Comparison
{
    /**
     * Require exact equality of the specified observable property.
     * @throws Finding
     */
    public static function same(mixed $expected, mixed $actual, string $property): void
    {
        if ($expected !== $actual) {
            throw new Finding($property . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
        }
    }

    /**
     * Preserve columns, NULLs and duplicate rows; normalize only driver scalar representation.
     * @param array<int, array<string, mixed>> $rows
     * @return list<array<string, string|null>>
      * @throws Finding
     */
    public static function rows(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            $values = [];
            foreach ($row as $column => $value) {
                if ($value !== null && !is_scalar($value)) {
                    throw new Finding('The SQL scenario returned a non-scalar value.');
                }
                $values[$column] = $value === null ? null : (string) $value;
            }
            $normalized[] = $values;
        }
        return $normalized;
    }
}
