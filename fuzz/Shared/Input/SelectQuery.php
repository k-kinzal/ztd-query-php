<?php

declare(strict_types=1);

namespace Fuzz\Shared\Input;

/**
 * Builds relational shapes around independently generated expressions and predicates.
 */
final class SelectQuery
{
    /**
     * Compose a relational query with stable result ordering.
     */
    public static function sql(Bytes $bytes, Schema $schema, string $table, string $other): string
    {
        $shape = $bytes->next(10);
        $expressions = new Expressions($bytes, $schema);
        $predicate = $expressions->predicate($other);
        $expression = $expressions->number();
        return match ($shape) {
            0 => "SELECT * FROM $table WHERE $predicate ORDER BY id",
            1 => "SELECT id, $expression AS value, label FROM $table WHERE $predicate ORDER BY id",
            2 => "SELECT a.id, b.id AS peer_id, a.v0, b.label FROM $table a LEFT JOIN $other b ON a.id = b.id WHERE " . $expressions->predicate($other, prefix: 'a.') . ' ORDER BY a.id, b.id',
            3 => "SELECT id, $expression AS value FROM (SELECT * FROM $table WHERE $predicate) AS derived_rows ORDER BY id",
            4 => "SELECT v0, COUNT(*) AS total, SUM(v0) AS amount FROM $table WHERE $predicate GROUP BY v0 HAVING COUNT(*) >= 1 ORDER BY v0",
            5 => "WITH chosen AS (SELECT * FROM $table WHERE $predicate) SELECT id, $expression AS value FROM chosen ORDER BY id",
            6 => "SELECT id, v0 FROM $table WHERE $predicate UNION ALL SELECT id, v0 FROM $other ORDER BY id, v0",
            7 => "SELECT id, (SELECT COALESCE(MAX(v0), 0) FROM $other) AS value FROM $table WHERE $predicate ORDER BY id",
            8 => "SELECT id, ROW_NUMBER() OVER (ORDER BY id) AS position FROM $table WHERE $predicate ORDER BY id",
            default => "SELECT DISTINCT v0 FROM $table WHERE $predicate ORDER BY v0",
        };
    }
}
