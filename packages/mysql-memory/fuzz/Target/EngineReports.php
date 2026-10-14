<?php

declare(strict_types=1);

namespace Fuzz\Target;

/**
 * Compares physical engine diagnostics using memory's empty-report contract.
 *
 * InnoDB monitor text and Performance Schema allocation reports describe engine
 * internals absent from memory. Native report payloads may differ; memory must
 * return no diagnostic rows. Columns, warnings, errors and table effects remain
 * exact. This rule does not apply to SHOW STATUS or its SQL-history counters.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-engine.html.
 */
final class EngineReports
{
    /**
     * The exception recorded after validating the physical report's result shape.
     */
    public const CONTRACT = 'physical-engine-status-empty-memory-report';

    /**
     * Selects plain reads of the physical engines whose report formats are checked.
     */
    public static function handles(string $sql): bool
    {
        return preg_match('/\A\s*SHOW\s+ENGINE\s+(?:ALL|INNODB|PERFORMANCE_SCHEMA)\s+STATUS\s*;?\s*\z/i', $sql) === 1;
    }

    /**
     * Validates each side before replacing only the engine-specific rows.
     *
     * @param array<string, mixed> $observation The complete PDO observation
     * @param bool $memory Whether the empty memory report is required
     * @return array<string, mixed> The observation with an explicit comparison contract, or unchanged on refusal
     */
    public function comparable(array $observation, bool $memory): array
    {
        $results = $observation['results'] ?? null;
        if (isset($observation['error']) || !is_array($results) || count($results) !== 1 || !is_array($results[0] ?? null)) {
            return $observation;
        }
        $result = $results[0];
        $columns = $result['columns'] ?? null;
        $rows = $result['rows'] ?? null;
        if (!is_array($columns) || count($columns) !== 3 || !is_array($rows)) {
            return $observation;
        }
        foreach (['Type', 'Name', 'Status'] as $index => $name) {
            if (!is_array($columns[$index] ?? null) || array_slice($columns[$index], 0, 3) !== [$name, '', 'VAR_STRING']) {
                return $observation;
            }
        }
        if ($memory ? $rows !== [] : !$this->native($rows)) {
            return $observation;
        }
        $result['rows'] = [];
        $observation['results'] = [$result];
        $observation['contracts'] = [self::CONTRACT];

        return $observation;
    }

    /**
     * Accepts only monitor text and named, nonnegative Performance Schema allocation values.
     *
     * @param array<mixed> $rows Native diagnostic rows
     */
    public function native(array $rows): bool
    {
        if ($rows === []) {
            return false;
        }
        foreach ($rows as $row) {
            if (!is_array($row) || count($row) !== 3 || !is_string($row[0] ?? null) || !is_string($row[1] ?? null) || !is_string($row[2] ?? null)) {
                return false;
            }
            if ($row[0] === 'InnoDB' ? ($row[1] !== '' || $row[2] === '') : ($row[0] !== 'performance_schema' || $row[1] === '' || preg_match('/\A[0-9]+\z/', $row[2]) !== 1)) {
                return false;
            }
        }

        return true;
    }
}
