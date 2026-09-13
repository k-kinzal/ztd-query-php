<?php

declare(strict_types=1);

namespace Fuzz\Support;

use ZtdQuery\Schema\TableDefinition;

/**
 * Generates bounded, type-appropriate rows and SQL values for pipeline property checks.
 */
final class FixtureRows
{
    /**
     * Generate random fixture rows for a table definition.
     *
     * @return array<int, array<string, int|float|string|bool>>
     */
    public static function generateFixtureRows(TableDefinition $definition, int $count): array
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $row = [];
            foreach ($definition->columns as $col) {
                $type = strtoupper($definition->columnTypes[$col] ?? 'TEXT');
                $row[$col] = self::generateValueForType($type, $i);
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Generate a random value appropriate for the given SQL type.
     */
    public static function generateValueForType(string $type, int $seed): int|float|string|bool
    {
        $baseType = preg_replace('/\(.*\)/', '', $type);
        $baseType = trim($baseType ?? $type);
        return match (true) {
            in_array($baseType, ['INT', 'INTEGER', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT', 'INT2', 'INT8'], true) => $seed + 1,
            in_array($baseType, ['REAL', 'DOUBLE', 'DOUBLE PRECISION', 'FLOAT', 'DECIMAL', 'NUMERIC'], true) => round($seed + 0.5, 2),
            in_array($baseType, ['BOOLEAN', 'BOOL'], true) => $seed % 2 === 0,
            default => 'val_' . $seed,
        };
    }

    /**
     * Build a VALUES clause with placeholder literals for all columns.
     */
    public static function buildInsertValues(TableDefinition $definition, \Faker\Generator $faker): string
    {
        $values = [];
        foreach ($definition->columns as $col) {
            $type = strtoupper($definition->columnTypes[$col] ?? 'TEXT');
            $baseType = preg_replace('/\(.*\)/', '', $type);
            $baseType = trim($baseType ?? $type);
            $values[] = match (true) {
                in_array($baseType, ['INT', 'INTEGER', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT', 'INT2', 'INT8'], true) => (string) $faker->numberBetween(1, 9999),
                in_array($baseType, ['REAL', 'DOUBLE', 'DOUBLE PRECISION', 'FLOAT', 'DECIMAL', 'NUMERIC'], true) => (string) round($faker->randomFloat(2, 0, 999), 2),
                in_array($baseType, ['BOOLEAN', 'BOOL'], true) => $faker->boolean() ? '1' : '0',
                default => "'" . str_replace("'", "''", $faker->word()) . "'",
            };
        }
        return implode(', ', $values);
    }

}
