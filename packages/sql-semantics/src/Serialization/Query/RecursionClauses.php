<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Query;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\CommonTableExpression;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;

/**
 * Writes the PostgreSQL SEARCH and CYCLE clauses of a common table expression.
 * @visibility SqlSemantics
 */
final class RecursionClauses
{
    /**
     * Writes the clauses that follow the definition's parenthesized query.
     *
     * @return list<Tree>
     */
    public static function write(CommonTableExpression $definition, Dialect $dialect): array
    {
        $parts = [];
        if ($definition->search !== null) {
            $parts[] = new Tree('search', [Build::keyword('SEARCH ' . $definition->search->order->value . ' BY'), self::names($definition->search->columns, $dialect), Build::keyword('SET'), Build::identifier([$definition->search->sequenceColumn], $dialect)]);
        }
        $cycle = $definition->cycle;
        if ($cycle !== null) {
            $marks = $cycle->markValue === null || $cycle->markDefault === null ? [] : [Build::keyword('TO'), $cycle->markValue->structure(), Build::keyword('DEFAULT'), $cycle->markDefault->structure()];
            $parts[] = new Tree('cycle', [Build::keyword('CYCLE'), self::names($cycle->columns, $dialect), Build::keyword('SET'), Build::identifier([$cycle->markColumn], $dialect), ...$marks, Build::keyword('USING'), Build::identifier([$cycle->pathColumn], $dialect)]);
        }
        return $parts;
    }

    /**
     * Writes a list of column names.
     *
     * @param non-empty-list<string> $columns
     */
    public static function names(array $columns, Dialect $dialect): Tree
    {
        return Build::separated(array_map(static fn (string $column): Tree => Build::identifier([$column], $dialect), $columns));
    }
}
