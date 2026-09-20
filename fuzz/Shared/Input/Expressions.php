<?php

declare(strict_types=1);

namespace Fuzz\Shared\Input;

/**
 * Composes expression trees; input mutations can change subtrees without replacing a query.
 */
final class Expressions
{
    /**
     * Use a local input slice and the generated schema.
     */
    public function __construct(private readonly Bytes $bytes, private readonly Schema $schema)
    {
    }

    /**
     * Generate a bounded arithmetic expression tree.
     */
    public function number(int $depth = 0, string $prefix = ''): string
    {
        $choice = $this->bytes->next($depth >= 3 ? 2 : 7);
        if ($choice === 0) {
            return $prefix . $this->schema->numbers[$this->bytes->next(count($this->schema->numbers))];
        }
        if ($choice === 1) {
            return (string) ($this->bytes->next(33) - 16);
        }
        $left = $this->number($depth + 1, $prefix);
        $right = $this->number($depth + 1, $prefix);
        return match ($choice) {
            2 => "($left + $right)",
            3 => "($left - $right)",
            4 => "COALESCE($left, $right)",
            5 => "CASE WHEN {$prefix}id > 2 THEN $left ELSE $right END",
            default => "ABS($left)",
        };
    }

    /**
     * Generate a bounded predicate tree using existing columns and tables.
     */
    public function predicate(string $other, int $depth = 0, string $prefix = ''): string
    {
        $choice = $this->bytes->next($depth >= 2 ? 3 : 7);
        if ($choice === 0) {
            $operator = ['=', '<', '>=', '<>'][$this->bytes->next(4)];
            return $this->number(1, $prefix) . " $operator " . $this->number(1, $prefix);
        }
        if ($choice === 1) {
            return $prefix . 'label IS ' . ($this->bytes->next(2) === 0 ? '' : 'NOT ') . 'NULL';
        }
        if ($choice === 2) {
            return $prefix . 'id <= ' . $this->bytes->next(12);
        }
        if ($choice === 3) {
            return "{$prefix}id IN (SELECT id FROM (SELECT id FROM $other WHERE v0 >= 0 LIMIT 1000) AS predicate_rows)";
        }
        $left = $this->predicate($other, $depth + 1, $prefix);
        if ($choice === 4) {
            return "NOT ($left)";
        }
        $right = $this->predicate($other, $depth + 1, $prefix);
        return '(' . $left . ($choice === 5 ? ' AND ' : ' OR ') . $right . ')';
    }
}
