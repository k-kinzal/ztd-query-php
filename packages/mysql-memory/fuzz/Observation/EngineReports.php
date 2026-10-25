<?php

declare(strict_types=1);

namespace Fuzz\Observation;

/**
 * Validates environment-dependent engine diagnostics while retaining result columns and row identities.
 * Missing engines, missing metric names, invalid quantities and unrecognized report contents remain failures.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-engine.html.
 */
final class EngineReports
{
    /**
     * @param array<string, mixed> $observation
     * @return array<string, mixed>
     */
    public function comparable(string $sql, array $observation): array
    {
        if (preg_match('/\A\s*SHOW\s+ENGINE\s+(?:ALL|INNODB|INNOBASE|PERFORMANCE_SCHEMA)\s+STATUS\s*;?\s*\z/i', $sql) !== 1 || isset($observation['error'])) {
            return $observation;
        }
        $results = $observation['results'] ?? null;
        if (!is_array($results) || count($results) !== 1 || !is_array($results[0] ?? null) || !is_array($results[0]['columns'] ?? null) || array_column($results[0]['columns'], 0) !== ['Type', 'Name', 'Status'] || !is_array($results[0]['rows'] ?? null)) {
            return $observation;
        }
        $rows = [];
        $metrics = [];
        $seen = [];
        foreach ($results[0]['rows'] as $row) {
            if (!is_array($row) || count($row) !== 3 || !is_string($row[0] ?? null) || !is_string($row[1] ?? null) || !is_string($row[2] ?? null) || isset($seen[$row[0] . ':' . $row[1]])) {
                return $observation;
            }
            $seen[$row[0] . ':' . $row[1]] = true;
            if ($row[0] === 'InnoDB' && $row[1] === '') {
                $status = (new InnoDbReport())->comparable($row[2]);
                if ($status === null) {
                    return $observation;
                }
                $rows[] = [$row[0], $row[1], $status];
            } elseif ($row[0] === 'performance_schema' && preg_match('/\A(?:[a-z_]+|\([a-z_]+\))\.(?:size|count|row_size|row_count|memory)\z/', $row[1]) === 1 && ctype_digit($row[2]) && bccomp($row[2], '18446744073709551615') <= 0) {
                $metrics[$row[1]] = $row[2];
                $rows[] = [$row[0], $row[1], '<environment:unsigned>'];
            } else {
                return $observation;
            }
        }
        if (!$this->consistent($metrics)) {
            return $observation;
        }
        $results[0]['rows'] = (new Observer())->bag($rows);
        $observation['results'] = $results;
        $observation['contracts'] = ['engine-report-contents-and-value-domains'];
        return $observation;
    }

    /**
     * The report's memory totals must agree with its record sizes and counts, on both engines.
     * @param array<string, numeric-string> $metrics
     */
    public function consistent(array $metrics): bool
    {
        $total = '0';
        foreach ($metrics as $name => $value) {
            if (!str_ends_with($name, '.memory') || $name === 'performance_schema.memory') {
                continue;
            }
            $base = substr($name, 0, -7);
            $size = $metrics[$base . '.size'] ?? $metrics[$base . '.row_size'] ?? null;
            $count = $metrics[$base . '.count'] ?? $metrics[$base . '.row_count'] ?? null;
            if ($size !== null && $count !== null && bccomp($value, bcmul($size, $count)) !== 0) {
                return false;
            }
            $total = bcadd($total, $value);
        }
        return $metrics === [] || (isset($metrics['performance_schema.memory']) && bccomp($metrics['performance_schema.memory'], $total) === 0);
    }
}
