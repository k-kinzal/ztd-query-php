<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

use mysqli;

/**
 * Writes one synthetic fixture row through a native prepared statement.
 */
final class FixtureRowWriter
{
    /**
     * @param array<string, mixed> $row
     */
    public function insertRow(mysqli $mysqli, string $table, array $row): void
    {
        $columns = array_keys($row);
        $placeholders = array_fill(0, count($columns), '?');
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(fn ($c) => "`$c`", $columns)),
            implode(', ', $placeholders)
        );
        $values = array_map(function ($v) {
            if (is_bool($v)) {
                return $v ? 1 : 0;
            }
            return $v;
        }, array_values($row));
        $types = str_repeat('s', count($values));
        $stmt = $mysqli->prepare($sql);
        assert($stmt !== false);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $stmt->close();
    }
}
